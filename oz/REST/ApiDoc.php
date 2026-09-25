<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace OZONE\Core\REST;

use Gobl\DBAL\Relations\Interfaces\VirtualRelationInterface;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use OpenApi\Annotations\AbstractAnnotation;
use OpenApi\Annotations as OA;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Operation;
use OpenApi\Annotations\Parameter;
use OpenApi\Annotations\Response;
use OpenApi\Annotations\Schema;
use OpenApi\Generator;
use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Forms\Form;
use OZONE\Core\Hooks\Events\EndRequestHook;
use OZONE\Core\OZone;
use OZONE\Core\REST\ApiDoc\GoblSchemaMapper;
use OZONE\Core\REST\ApiDoc\OperationBuilder;
use OZONE\Core\REST\ApiDoc\ParameterBuilder;
use OZONE\Core\REST\ApiDoc\ResponseBuilder;
use OZONE\Core\REST\ApiDoc\SchemaBuilder;
use OZONE\Core\REST\Events\ApiDocReady;
use OZONE\Core\REST\Interfaces\ApiDocProviderInterface;
use OZONE\Core\REST\Services\ApiDocService;
use OZONE\Core\Router\Route;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Class ApiDoc.
 *
 * The OpenAPI document of the project, filled by every enabled {@see ApiDocProviderInterface}.
 *
 * The work is done by the builders ({@see self::schemas()}, {@see self::gobl()},
 * {@see self::parameters()}, {@see self::responses()}, {@see self::operations()}); the other
 * methods delegate to them.
 */
final class ApiDoc implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	protected OpenApi $openapi;
	protected OA\Info $api_info;
	private static ?self $instance = null;

	private readonly SchemaBuilder $schemas;
	private readonly GoblSchemaMapper $gobl;
	private readonly ParameterBuilder $parameters;
	private readonly ResponseBuilder $responses;
	private readonly OperationBuilder $operations;

	/**
	 * ApiDoc constructor.
	 *
	 * The spec keeps no context: what needs the request reads the current one ({@see context()}).
	 */
	private function __construct(string $title, string $version)
	{
		$context            = Generator::$context = $this->createContext();
		$this->api_info     = new OA\Info([
			'title'   => $title,
			'version' => $version,
		]);
		$this->openapi = new OpenApi([
			'info'     => $this->api_info,
			'openapi'  => OpenApi::VERSION_3_1_0,
			'_context' => $context,
		]);

		$this->schemas    = new SchemaBuilder($this->openapi);
		$this->gobl       = new GoblSchemaMapper($this->schemas);
		$this->parameters = new ParameterBuilder($this->schemas);
		$this->responses  = new ResponseBuilder($this->schemas, $this->gobl);
		$this->operations = new OperationBuilder(
			$this->openapi,
			$this->schemas,
			$this->parameters,
			$this->responses
		);

		$this->loadProviders();
	}

	/**
	 * Drops the instance, so the next request builds its own.
	 *
	 * The spec is built per request, from the routes and the request's host: kept across requests in
	 * a persistent worker, it would serve the first request's documentation forever.
	 *
	 * @internal on {@see EndRequestHook}
	 */
	public static function release(): void
	{
		self::$instance = null;
	}

	/**
	 * Gets the ApiDoc instance of the current request.
	 */
	public static function get(): static
	{
		if (!isset(self::$instance)) {
			self::$instance = new self('API Documentation', '1.0.0');
		}

		return self::$instance;
	}

	/**
	 * Schema primitives and components.
	 */
	public function schemas(): SchemaBuilder
	{
		return $this->schemas;
	}

	/**
	 * Schemas of Gobl types and tables.
	 */
	public function gobl(): GoblSchemaMapper
	{
		return $this->gobl;
	}

	/**
	 * Parameters, including the O'Zone API query parameters.
	 */
	public function parameters(): ParameterBuilder
	{
		return $this->parameters;
	}

	/**
	 * Responses and request bodies.
	 */
	public function responses(): ResponseBuilder
	{
		return $this->responses;
	}

	/**
	 * Paths and operations.
	 */
	public function operations(): OperationBuilder
	{
		return $this->operations;
	}

	/**
	 * Gets the OpenApi view render data.
	 *
	 * @return array
	 */
	public function viewInject(): array
	{
		return [
			'api_doc_title'    => $this->api_info->title,
			'api_doc_spec_url' => context()->buildRouteUri(ApiDocService::API_DOC_SPEC_ROUTE),
			'api_doc_spec'     => $this->openapi->toJson(),
		];
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'spec' => \json_decode($this->openapi->toJson()),
		];
	}

	/**
	 * Adds a new tag.
	 *
	 * @param string $name        the tag name
	 * @param string $description the tag description
	 * @param array  $properties  the tag properties
	 */
	public function addTag(string $name, string $description = '', array $properties = []): OA\Tag
	{
		$tag = new OA\Tag([
			'name'        => $name,
			'description' => $description,
		] + $properties);

		SchemaBuilder::push($this->openapi, 'tags', $tag);

		return $tag;
	}

	/**
	 * Adds a new server.
	 *
	 * @param string $url         the server URL
	 * @param string $description the server description
	 */
	public function addServer(string $url, string $description = ''): OA\Server
	{
		$server = new OA\Server([
			'url'         => $url,
			'description' => $description,
		]);
		SchemaBuilder::push($this->openapi, 'servers', $server);

		return $server;
	}

	/**
	 * Adds an "OZone Form System" guide tag to the API documentation.
	 *
	 * Describes form discovery, the resume lifecycle, and the x-oz-form extension.
	 * Call once from a global ApiDocProviderInterface::apiDoc() implementation.
	 */
	public function addFormSystemGuide(): OA\Tag
	{
		$discovery_header  = Settings::get('oz.request', 'OZ_FORM_DISCOVERY_HEADER_NAME');
		$resume_header     = Settings::get('oz.request', 'OZ_FORM_RESUME_HEADER_NAME');
		$resume_ref_header = Settings::get('oz.request', 'OZ_FORM_RESUME_REF_HEADER_NAME');

		$desc = <<<DESC
How OZone handles form validation across the API.

## Form Discovery (`{$discovery_header}: ?1`)

Any route with a form attached supports **Form Discovery**.
Add `{$discovery_header}: ?1` to the request and the handler is skipped;
the server returns the form bundle in `form`, next to `data` in the envelope - useful for building
dynamic UIs.

Every rule set of a form is sent (`expect`, `ensure`, a fieldset's own, the `if` conditions), so a
client can check a form while it is filled, in the order the server does. A set comparing against a
secret server value is sent as `{ref, \$secret: true}`: ask the `evaluate` endpoint of a form session
about it. A server value the client may see is sent as its preview, `{\$preview: {value}}`; the server
compares against its own value on submit.

Each operation carries an `x-oz-form` extension describing the form doc policy:

| Policy | `requestBody` in spec | Meaning |
|--------|-----------------------|---------|
| `static` | yes | Schema is known at doc-generation time. |
| `dynamic` | no | Schema is built at runtime; an `init_form` preview may be present. |
| `opaque` | no | Schema is not exposed in the spec. |

`x-oz-form` fields: `policy`, `resumable` (bool), `require_real_context` (bool),
`provider_name` (when resumable), `init_form` (when dynamic and provider exposes one).

## Form Resume (`{$resume_header}: ?1`)

For routes backed by a resumable-form provider, add `{$resume_header}: ?1` to drive
the full session lifecycle (init / next / back / cancel / evaluate) on that route directly.
The session reference is returned in `data.resume_ref` and must be re-sent on all
subsequent calls via the `{$resume_ref_header}` header.

Providers that do not require a real route context are also reachable through the
standalone `/form/:provider/...` endpoints documented under **Resumable Forms**.
DESC;

		return $this->addTag('OZone Form System', $desc);
	}

	/**
	 * @see OperationBuilder::path()
	 */
	public function path(string $path): OA\PathItem
	{
		return $this->operations->path($path);
	}

	/**
	 * @see OperationBuilder::add()
	 *
	 * @param array<Response> $responses
	 */
	public function addOperation(
		string $path,
		string $method,
		string $summary,
		array $responses,
		array $properties = []
	): Operation {
		return $this->operations->add($path, $method, $summary, $responses, $properties);
	}

	/**
	 * @see OperationBuilder::fromRoute()
	 */
	public function addOperationFromRoute(
		Route|string $route,
		string $method,
		string $summary,
		array $responses,
		array $properties = [],
		array $path_params_values = []
	): Operation {
		return $this->operations->fromRoute($route, $method, $summary, $responses, $properties, $path_params_values);
	}

	/**
	 * @see OperationBuilder::pushExtension()
	 */
	public static function pushExtension(Operation $op, string $name, array $value): void
	{
		OperationBuilder::pushExtension($op, $name, $value);
	}

	/**
	 * @see ParameterBuilder::parameter()
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in
	 */
	public function parameter(
		string $name,
		Schema $schema,
		string $description,
		string $in = 'path',
		array $properties = []
	): Parameter {
		return $this->parameters->parameter($name, $schema, $description, $in, $properties);
	}

	/**
	 * @see ParameterBuilder::page()
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in
	 */
	public function apiPageParameter(string $in = 'query'): Parameter
	{
		return $this->parameters->page($in);
	}

	/**
	 * @see ParameterBuilder::max()
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in
	 */
	public function apiMaxParameter(string $in = 'query', int $default_max = 10): Parameter
	{
		return $this->parameters->max($in, $default_max);
	}

	/**
	 * @see ParameterBuilder::cursor()
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in
	 */
	public function apiCursorParameter(string $in = 'query'): Parameter
	{
		return $this->parameters->cursor($in);
	}

	/**
	 * @see ParameterBuilder::cursorColumn()
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in
	 */
	public function apiCursorColumnParameter(string $in = 'query'): Parameter
	{
		return $this->parameters->cursorColumn($in);
	}

	/**
	 * @see ParameterBuilder::cursorDir()
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in
	 */
	public function apiCursorDirParameter(string $in = 'query'): Parameter
	{
		return $this->parameters->cursorDir($in);
	}

	/**
	 * @see ParameterBuilder::collection()
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in
	 * @param string[]                         $collections
	 */
	public function apiCollectionParameter(string $in = 'query', array $collections = []): Parameter
	{
		return $this->parameters->collection($in, $collections);
	}

	/**
	 * @see ParameterBuilder::orderBy()
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in
	 */
	public function apiOrderByParameter(string $in = 'query'): Parameter
	{
		return $this->parameters->orderBy($in);
	}

	/**
	 * @see ParameterBuilder::relations()
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in
	 * @param string[]                         $relations
	 */
	public function apiRelationsParameter(string $in = 'query', array $relations = []): Parameter
	{
		return $this->parameters->relations($in, $relations);
	}

	/**
	 * @see ParameterBuilder::filters()
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in
	 */
	public function apiFiltersParameter(string $in = 'query', ?Table $table = null): Parameter
	{
		return $this->parameters->filters($in, $table);
	}

	/**
	 * @see ResponseBuilder::response()
	 *
	 * @param array<string, OA\Attachable|OA\MediaType> $content
	 */
	public function response(int $http_status_code, string $description, array $content): Response
	{
		return $this->responses->response($http_status_code, $description, $content);
	}

	/**
	 * @see ResponseBuilder::requestBody()
	 *
	 * @param array<OA\Attachable|OA\MediaType> $content
	 */
	public function requestBody(array $content): OA\RequestBody
	{
		return $this->responses->requestBody($content);
	}

	/**
	 * @see ResponseBuilder::requestBodyFromForm()
	 */
	public function requestBodyFromForm(Form $form): OA\RequestBody
	{
		return $this->responses->requestBodyFromForm($form);
	}

	/**
	 * @see ResponseBuilder::success()
	 */
	public function success(
		array|Schema $data,
		?string $description = '',
		?string $message = 'OK',
		int $http_status_code = 200,
	): Response {
		return $this->responses->success($data, $description, $message, $http_status_code);
	}

	/**
	 * @see ResponseBuilder::error()
	 */
	public function error(
		array|Schema $data,
		?string $description = '',
		?string $message = 'OZ_ERROR_INTERNAL',
		int $http_status_code = 200,
	): Response {
		return $this->responses->error($data, $description, $message, $http_status_code);
	}

	/**
	 * @see ResponseBuilder::payload()
	 */
	public function apiJSONResponsePayload(int $ozone_error_code, string $message, array|Schema $data): OA\MediaType
	{
		return $this->responses->payload($ozone_error_code, $message, $data);
	}

	/**
	 * @see ResponseBuilder::paginated()
	 *
	 * @param array<string,Schema> $properties
	 */
	public function apiPaginated(array $properties, int $default_max = 10): Schema
	{
		return $this->responses->paginated($properties, $default_max);
	}

	/**
	 * @see ResponseBuilder::cursorPaginated()
	 *
	 * @param array<string,Schema> $properties
	 */
	public function apiCursorPaginated(array $properties, int $default_max = 10): Schema
	{
		return $this->responses->cursorPaginated($properties, $default_max);
	}

	/**
	 * @see GoblSchemaMapper::entitySchemaForRead()
	 */
	public function entitySchemaForRead(string|Table $table): Schema
	{
		return $this->gobl->entitySchemaForRead($table);
	}

	/**
	 * @see GoblSchemaMapper::entitySchemaForCreate()
	 */
	public function entitySchemaForCreate(string|Table $table): Schema
	{
		return $this->gobl->entitySchemaForCreate($table);
	}

	/**
	 * @see GoblSchemaMapper::entitySchemaForUpdate()
	 */
	public function entitySchemaForUpdate(string|Table $table): Schema
	{
		return $this->gobl->entitySchemaForUpdate($table);
	}

	/**
	 * @see GoblSchemaMapper::declareTypeSchemaProvider()
	 *
	 * @param callable(TypeInterface):Schema $provider
	 */
	public static function declareGoblTypeToSchemaProvider(string $name, callable $provider): void
	{
		GoblSchemaMapper::declareTypeSchemaProvider($name, $provider);
	}

	/**
	 * @see GoblSchemaMapper::typeSchema()
	 */
	public function typeSchema(TypeInterface $type): Schema
	{
		return $this->gobl->typeSchema($type);
	}

	/**
	 * @see GoblSchemaMapper::virtualRelationTypeSchema()
	 */
	public function virtualRelationTypeSchema(VirtualRelationInterface $vr): Schema
	{
		return $this->gobl->virtualRelationTypeSchema($vr);
	}

	/**
	 * @see GoblSchemaMapper::tableMeta()
	 *
	 * @return array{singular_name: string, plural_name: string, description: string, use_an: bool}
	 */
	public function tableMeta(Table $table): array
	{
		return $this->gobl->tableMeta($table);
	}

	/**
	 * @see SchemaBuilder::json()
	 */
	public function json(Schema $schema): OA\MediaType
	{
		return $this->schemas->json($schema);
	}

	/**
	 * @see SchemaBuilder::component()
	 *
	 * @param callable():AbstractAnnotation $factory
	 */
	public function component(string $kind, string $name, callable $factory, array $options = []): Schema
	{
		return $this->schemas->component($kind, $name, $factory, $options);
	}

	/**
	 * @see SchemaBuilder::object()
	 *
	 * @param Schema[] $properties
	 */
	public function object(array $properties, array $options = []): Schema
	{
		return $this->schemas->object($properties, $options);
	}

	/**
	 * @see SchemaBuilder::array()
	 */
	public function array(?Schema $item = null, array $options = []): Schema
	{
		return $this->schemas->array($item, $options);
	}

	/**
	 * @see SchemaBuilder::integer()
	 */
	public function integer(?string $description = null, array $options = []): Schema
	{
		return $this->schemas->integer($description, $options);
	}

	/**
	 * @see SchemaBuilder::string()
	 */
	public function string(?string $description = null, array $options = []): Schema
	{
		return $this->schemas->string($description, $options);
	}

	/**
	 * @see SchemaBuilder::boolean()
	 */
	public function boolean(?string $description = null, array $options = []): Schema
	{
		return $this->schemas->boolean($description, $options);
	}

	/**
	 * @see SchemaBuilder::type()
	 *
	 * @param string|string[] $type
	 */
	public function type(array|string $type, ?string $description = null, array $options = []): Schema
	{
		return $this->schemas->type($type, $description, $options);
	}

	/**
	 * @see SchemaBuilder::push()
	 *
	 * @param null|callable(mixed):bool $predicate
	 */
	public static function push(object $to, string $prop, mixed $value, ?callable $predicate = null): void
	{
		SchemaBuilder::push($to, $prop, $value, $predicate);
	}

	/**
	 * @see SchemaBuilder::toHumanReadable()
	 */
	public static function toHumanReadable(string $name): string
	{
		return SchemaBuilder::toHumanReadable($name);
	}

	/**
	 * Create the OpenApi context.
	 *
	 * @return \OpenApi\Context
	 */
	private function createContext(): \OpenApi\Context
	{
		$logger = new class extends AbstractLogger {
			#[Override]
			public function log($level, string|Stringable $message, array $context = []): void
			{
				oz_trace($level . ': ' . $message, $context);
			}
		};

		return new \OpenApi\Context([
			'logger'  => $logger,
		]);
	}

	/**
	 * Load the API documentation providers.
	 */
	private function loadProviders(): void
	{
		$api  = OZone::getApiRoutesProviders();
		$web  = OZone::getWebRoutesProviders();

		$providers = $api + $web;

		foreach ($providers as $provider => $enabled) {
			if (!$enabled || !\is_subclass_of($provider, ApiDocProviderInterface::class)) {
				continue;
			}

			$provider::apiDoc($this);
		}

		(new ApiDocReady($this))->dispatch();
	}
}
