<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\Core\REST\Traits;

use BackedEnum;
use Gobl\DBAL\Operator;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeBool;
use Gobl\DBAL\Types\TypeDate;
use Gobl\DBAL\Types\TypeDecimal;
use Gobl\DBAL\Types\TypeEnum;
use Gobl\DBAL\Types\TypeFloat;
use Gobl\DBAL\Types\TypeInt;
use Gobl\DBAL\Types\TypeList;
use Gobl\DBAL\Types\TypeMap;
use Gobl\DBAL\Types\TypeString;
use InvalidArgumentException;
use OpenApi\Annotations\AbstractAnnotation;
use OpenApi\Annotations as OA;
use OpenApi\Annotations\Operation;
use OpenApi\Annotations\Parameter;
use OpenApi\Annotations\Response;
use OpenApi\Annotations\Schema;
use OpenApi\Generator;
use OpenApi\Processors\MergeJsonContent;
use OZONE\Core\App\JSONResponse;
use OZONE\Core\Columns\Types\TypeEmail;
use OZONE\Core\Columns\Types\TypePassword;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\REST\RESTFulAPIRequest;
use OZONE\Core\Router\Route;

/**
 * Trait ApiDocManipulationTrait.
 */
trait ApiDocManipulationTrait
{
	/**
	 * @var array<string , OA\PathItem> The paths
	 */
	protected array $paths                 = [];
	protected bool $include_private_column = false;

	/**
	 * @var array<string, callable(TypeInterface):Schema> The gobl types to schema providers
	 */
	protected static array $gobl_types_to_schema_providers = [];

	/**
	 * Adds a new tag.
	 *
	 * @param string $name        the tag name
	 * @param string $description the tag description
	 * @param array  $properties  the tag properties
	 *
	 * @return OA\Tag
	 */
	public function addTag(string $name, string $description = '', array $properties = []): OA\Tag
	{
		$tag = new OA\Tag([
			'name'        => $name,
			'description' => $description,
		] + $properties);

		self::push($this->openapi, 'tags', $tag);

		return $tag;
	}

	/**
	 * Adds a new server.
	 *
	 * @param string $url         the server URL
	 * @param string $description the server description
	 *
	 * @return OA\Server
	 */
	public function addServer(string $url, string $description = ''): OA\Server
	{
		$server = new OA\Server([
			'url'         => $url,
			'description' => $description,
		]);
		self::push($this->openapi, 'servers', $server);

		return $server;
	}

	/**
	 * Creates new {@see OA\PathItem} or returns an existing.
	 *
	 * @param string $path
	 *
	 * @return OA\PathItem
	 */
	public function path(string $path): OA\PathItem
	{
		if (!isset($this->paths[$path])) {
			$p = new OA\PathItem([
				'path' => $path,
			]);
			$this->paths[$path] = $p;

			self::push($this->openapi, 'paths', $p);
		}

		return $this->paths[$path];
	}

	/**
	 * Adds a new operation from a route.
	 *
	 * > If the path contains dynamic parameters, you can provide the values
	 * > for these parameters using the `$path_params_values` argument.
	 * > You can preserve some path parameters by omitting them from the `$path_params_values` argument.
	 *
	 * @param Route|string $route              The route or the route name to add
	 * @param string       $method             HTTP method
	 * @param string       $summary            Summary of the route
	 * @param array        $responses          OA\Response[]
	 * @param array        $properties         Additional operation properties
	 * @param array        $path_params_values the path parameters values
	 *
	 * @return Operation
	 */
	public function addOperationFromRoute(
		Route|string $route,
		string $method,
		string $summary,
		array $responses,
		array $properties = [],
		array $path_params_values = []
	): Operation {
		if (\is_string($route)) {
			$route = $this->context->getRouter()->requireRoute($route);
		}

		if (!$route->accept($method)) {
			throw new InvalidArgumentException(\sprintf(
				'Route %s does not accept method %s',
				$route->getName(),
				$method
			));
		}

		$route_path     = $route->getPath();
		$route_params   = \array_unique($route->getPathParams());
		$exclude_params = [];

		if (!empty($path_params_values)) {
			foreach ($route_params as $key) {
				if (!isset($path_params_values[$key])) {
					// preserve the path parameter
					$path_params_values[$key] = ':' . $key;
				} else {
					$exclude_params[$key]     = true;
				}
			}

			$route_path = $route->buildPath($this->context, $path_params_values);
		}

		$op                       = $this->addOperation($route_path, $method, $summary, $responses, $properties);
		$path_item                = $this->path($route_path);
		$declared_params_patterns = $route->getDeclaredParams();

		foreach ($route_params as $param) {
			if (isset($exclude_params[$param])) {
				continue;
			}

			$pattern  = $declared_params_patterns[$param] ?? Route::DEFAULT_PARAM_PATTERN;
			$oa_param = $this->parameter(
				$param,
				$this->string(null, [
					'pattern' => $pattern,
				]),
				\sprintf('The parameter `%s` should match the pattern `%s`.', $param, $pattern)
			);

			self::push(
				$path_item,
				'parameters',
				$oa_param,
				// we add the path parameter only if it does not exist on this path item
				// as multiple operations can share the same path item
				// no checking may result in multiple parameters with the same name
				static fn ($a) => $a->name === $param
			);
		}

		return $op;
	}

	/**
	 * Create a new parameter.
	 *
	 * @param string                           $name        the parameter name
	 * @param Schema                           $schema      the parameter schema
	 * @param string                           $description the parameter description
	 * @param 'cookie'|'header'|'path'|'query' $in          the parameter location
	 * @param array                            $properties  the parameter properties
	 *
	 * @return Parameter
	 */
	public function parameter(
		string $name,
		Schema $schema,
		string $description,
		string $in = 'path',
		array $properties = []
	): Parameter {
		return new Parameter([
			'name'        => $name,
			'schema'      => $schema,
			'in'          => $in,
			'description' => $description,
		] + $properties);
	}

	/**
	 * Adds a new operation.
	 *
	 * @param string          $path       the request path
	 * @param string          $method     the request method
	 * @param string          $summary    the request summary
	 * @param array<Response> $responses  the request responses
	 * @param array           $properties the request properties
	 *
	 * @return Operation
	 */
	public function addOperation(
		string $path,
		string $method,
		string $summary,
		array $responses,
		array $properties = []
	): Operation {
		$method_lower = \strtolower($method);
		$options      = [
			'summary'   => $summary,
			'responses' => $responses,
		] + $properties;
		$operation = match ($method_lower) {
			'get'     => new OA\Get($options),
			'post'    => new OA\Post($options),
			'put'     => new OA\Put($options),
			'patch'   => new OA\Patch($options),
			'options' => new OA\Options($options),
			'delete'  => new OA\Delete($options),
			'head'    => new OA\Head($options),
			'trace'   => new OA\Trace($options),
			default   => throw new InvalidArgumentException("Unsupported method: {$method}"),
		};

		$p = $this->path($path);

		$p->{$method_lower} = $operation;

		return $operation;
	}

	/**
	 * Creates a new response.
	 *
	 * @param int                                       $http_status_code the response HTTP status code
	 * @param string                                    $description      the response description
	 * @param array<string, OA\Attachable|OA\MediaType> $content          the response content
	 */
	public function response(int $http_status_code, string $description, array $content): Response
	{
		return new Response([
			'response'    => $http_status_code,
			'description' => $description,
			'content'     => $content,
		]);
	}

	/**
	 * Create a new request body.
	 *
	 * @param array<string, OA\Attachable|OA\MediaType> $content
	 *
	 * @return OA\RequestBody
	 */
	public function requestBody(array $content): OA\RequestBody
	{
		return new OA\RequestBody([
			'content' => $content,
		]);
	}

	/**
	 * Create an ozone success response.
	 *
	 * @param array|Schema $data             the data
	 * @param null|string  $description      the description
	 * @param null|string  $message          the message
	 * @param int          $http_status_code the HTTP status code
	 *
	 * @return Response
	 */
	public function success(
		array|Schema $data,
		?string $description = '',
		?string $message = 'OK',
		int $http_status_code = 200,
	): Response {
		return $this->response($http_status_code, $description, [
			'application/json' => $this->apiJSONResponsePayload(JSONResponse::RESPONSE_CODE_SUCCESS, $message, $data),
		]);
	}

	/**
	 * Create an ozone error response.
	 *
	 * @param array|Schema $data             the data
	 * @param null|string  $description      the description
	 * @param null|string  $message          the message
	 * @param int          $http_status_code the HTTP status code
	 *
	 * @return Response
	 */
	public function error(
		array|Schema $data,
		?string $description = '',
		?string $message = 'OZ_ERROR_INTERNAL',
		int $http_status_code = 200,
	): Response {
		return $this->response($http_status_code, $description, [
			'application/json' => $this->apiJSONResponsePayload(JSONResponse::RESPONSE_CODE_ERROR, $message, $data),
		]);
	}

	/**
	 * Creates the O'Zone API JSON response schema with a message and data.
	 *
	 * @param int          $ozone_error_code the error code: 0 for success and 1 for error
	 * @param string       $message          the message
	 * @param array|Schema $data             the data
	 */
	public function apiJSONResponsePayload(int $ozone_error_code, string $message, array|Schema $data): OA\MediaType
	{
		$data = $data instanceof Schema ? $data : $this->object($data);

		$utime = $this->integer('The response UNIX timestamp.');
		$stime = $this->integer('The response auth expiration UNIX timestamp.');

		return $this->json($this->object([
			'error' => $this->integer('Indicate if there is an error: `0` for success, `1` for error.', ['default' => $ozone_error_code]),
			'msg'   => $this->string('The error/success message.', [
				'default' => $message,
			]),
			'data'  => $data,
			'utime' => $utime,
			'stime' => $stime,
		]));
	}

	/**
	 * Create a O'Zone API paginated schema.
	 *
	 * @param array<string,Schema> $properties  The properties
	 * @param int                  $default_max The default maximum number of items per page
	 *
	 * @return Schema
	 */
	public function apiPaginated(array $properties, int $default_max = 10): Schema
	{
		return $this->object($properties + [
			'page'      => $this->integer('The current page number.', [
				'default' => 1,
			]),
			'max' => $this->integer('The maximum number of items per page.', [
				'default' => $default_max,
			]),
			'total' => $this->integer('The total number of items.'),
		]);
	}

	/**
	 * Create the O'Zone API page parameter.
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in The parameter location
	 *
	 * @return Parameter
	 */
	public function apiPageParameter(string $in = 'query'): Parameter
	{
		return $this->parameter(
			RESTFulAPIRequest::PAGE_PARAM,
			$this->integer('The desired page number.', [
				'default' => 1,
			]),
			'The desired page number.',
			$in
		);
	}

	/**
	 * Create the O'Zone API max parameter.
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in          The parameter location
	 * @param int                              $default_max The default maximum number of items per page
	 *
	 * @return Parameter
	 */
	public function apiMaxParameter(string $in = 'query', int $default_max = 10): Parameter
	{
		return $this->parameter(
			RESTFulAPIRequest::MAX_PARAM,
			$this->integer('The maximum number of items per page.', [
				'default' => $default_max,
			]),
			'The maximum number of items per page.',
			$in
		);
	}

	/**
	 * Create the O'Zone API collection parameter.
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in          The parameter location
	 * @param string[]                         $collections The available collections
	 *
	 * @return Parameter
	 */
	public function apiCollectionParameter(string $in = 'query', array $collections = []): Parameter
	{
		$sc = $this->string('The collection name.');

		if (!empty($collections)) {
			$sc->enum = $collections;
		}

		return $this->parameter(
			RESTFulAPIRequest::COLLECTION_PARAM,
			$sc,
			'The collection name.',
			$in
		);
	}

	/**
	 * Create the O'Zone API order by parameter.
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in The parameter location
	 *
	 * @return Parameter
	 */
	public function apiOrderByParameter(string $in = 'query'): Parameter
	{
		$list_sep = RESTFulAPIRequest::ORDER_BY_DELIMITER;
		$rule_sep = RESTFulAPIRequest::ORDER_BY_DELIMITER_ASC_DESC;

		$sc       = $this->string('The ordering rules parameter');
		$desc     = <<<DESC
The ordering rules parameter.

A list of ordering rules separated by the delimiter `{$list_sep}`.
Each ordering rule is a column name followed by the delimiter `{$rule_sep}` and the order direction (`asc` or `desc`).

> Example: `field_one{$rule_sep}asc{$list_sep}field_two{$rule_sep}desc`.
DESC;

		return $this->parameter(
			RESTFulAPIRequest::ORDER_BY_PARAM,
			$sc,
			$desc,
			$in
		);
	}

	/**
	 * Create the O'Zone API relations parameter.
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in        The parameter location
	 * @param string[]                         $relations The available relations
	 *
	 * @return Parameter
	 */
	public function apiRelationsParameter(string $in = 'query', array $relations = []): Parameter
	{
		$rel_sep               = RESTFulAPIRequest::RELATIONS_DELIMITER;
		$sc                    = $this->string('The relations parameter.');
		$allowed_relation_text = '';

		if (!empty($relations)) {
			$sc->enum              = $relations;
			$rel_strings           = \implode($rel_sep, $relations);
			$allowed_relation_text = "Allowed relations are: `{$rel_strings}`.\n";
		}

		$desc        = <<<DESC
The relations parameter.

A list of relations separated by the delimiter `{$rel_sep}`.
{$allowed_relation_text}
> Note: Only non paginated relations are allowed. For paginated relations use dedicated endpoints.

> Example: `relation_one{$rel_sep}relation_two`.
DESC;

		return $this->parameter(
			RESTFulAPIRequest::RELATIONS_PARAM,
			$sc,
			$desc,
			$in
		);
	}

	/**
	 * Create the O'Zone API filters parameter.
	 *
	 * ```
	 * [
	 *    [['foo', 'eq', 'value'], 'OR', ['bar', 'lt', 8]], 'AND', ['baz', 'is_null']]
	 *  ]
	 * ```
	 *
	 * @param 'cookie'|'header'|'path'|'query' $in The parameter location
	 *
	 * @return Parameter
	 */
	public function apiFiltersParameter(string $in = 'query'): Parameter
	{
		$filters_param               = RESTFulAPIRequest::FILTERS_PARAM;
		$allowed_operators_str       = \implode(', ', \array_map(static fn ($op) => "`{$op->value}`", Operator::cases()));
		$op_in                       = Operator::IN->value;
		$op_not_in                   = Operator::NOT_IN->value;
		$desc                        = <<<DESC
The filters parameter.

Use `{$filters_param}` to apply complex filtering logic to your query.
The filter structure consists of conditions ([`field`, `operator`, `value`]),
logical connectors (`AND`, `OR`), and nested conditions (arrays within arrays).

Each condition follows this format:
```
['field', 'operator', 'value']
```

Where:
- `field` is the column name.
- `operator` is the comparison operator: {$allowed_operators_str}

> Note: The `{$op_in}` and `{$op_not_in}` operators require an array of values.

Examples:

```json
[
    [
        ['foo', 'eq', 'value', 'OR', 'bar', 'lt', 8]
    ],
    'AND', ['baz', 'is_null']
]
```

You can also use this:

```json
[
    'foo', 'eq', 'value',
    'AND',
    'bar', 'in', [1, 2, 3],
    'AND',
    'baz', 'is_null'
]
```

DESC;

		return $this->parameter(
			$filters_param,
			$this->array(null, ['description' => 'The desired filters.']),
			$desc,
			$in
		);
	}

	/**
	 * Create a JSON media type.
	 */
	public function json(Schema $schema): OA\MediaType
	{
		/**
		 * Not using shortcut {@see OA\JsonContent} because
		 * we are not using processors {@see MergeJsonContent} as seen here {@see Generator::getProcessorPipeline()}.
		 */
		return new OA\MediaType([
			'mediaType' => 'application/json',
			'schema'    => $schema,
		]);
	}

	/**
	 * Returns schema type for a table entity read.
	 *
	 * @param string|Table $table The table or the table name
	 *
	 * @return Schema
	 */
	public function entitySchemaForRead(string|Table $table): Schema
	{
		return $this->entitySchema($table, 'read');
	}

	/**
	 * Returns schema type for a table entity creation.
	 *
	 * @param string|Table $table The table or the table name
	 *
	 * @return Schema
	 */
	public function entitySchemaForCreate(string|Table $table): Schema
	{
		return $this->entitySchema($table, 'create');
	}

	/**
	 * Returns schema type for a table entity update.
	 *
	 * @param string|Table $table The table or the table name
	 *
	 * @return Schema
	 */
	public function entitySchemaForUpdate(string|Table $table): Schema
	{
		return $this->entitySchema($table, 'update');
	}

	/**
	 * Create a reusable component.
	 *
	 * @param 'examples'|'headers'|'links'|'parameters'|'requestBodies'|'responses'|'schemas'|'securitySchemes' $kind    the component kind
	 * @param string                                                                                            $name    the component name
	 * @param callable():AbstractAnnotation                                                                     $factory the component factory
	 * @param array                                                                                             $options the component options
	 *
	 * @return Schema
	 */
	public function component(string $kind, string $name, callable $factory, array $options = []): Schema
	{
		/** @psalm-suppress InvalidPropertyAssignmentValue */
		if (self::isUndefined($this->openapi->components)) {
			$this->openapi->components = [];
		}

		/** @psalm-suppress UndefinedMethod */
		if (!isset($this->openapi->components[$kind][$name])) {
			/** @psalm-suppress UndefinedMethod */
			$this->openapi->components[$kind][$name] = $factory();
		}

		return new Schema([
			'ref'     => "#/components/{$kind}/{$name}",
		] + $options);
	}

	/**
	 * Declare a gobl type to schema provider.
	 *
	 * @param string                         $name     the gobl type name
	 * @param callable(TypeInterface):Schema $provider the provider callable
	 */
	public static function declareGoblTypeToSchemaProvider(string $name, callable $provider): void
	{
		self::$gobl_types_to_schema_providers[$name] = $provider;
	}

	/**
	 * Create a schema for a gobl type.
	 *
	 * @param TypeInterface $type
	 *
	 * @return Schema
	 */
	public function typeSchema(TypeInterface $type): Schema
	{
		$factory = self::$gobl_types_to_schema_providers[$type->getName()] ?? null;

		if ($factory) {
			$schema = $factory($type);
		} else {
			$schema = $this->goblTypeToSchema($type) ?? $this->goblTypeToSchema($type->getBaseType());
		}

		if (null === $schema) {
			throw (new RuntimeException("Unsupported type: {$type->getName()}"))->suspectObject($type);
		}

		return $schema;
	}

	/**
	 * Create schema with type `object`.
	 *
	 * @param Schema[] $properties The object properties
	 * @param array    $options    The schema options
	 *
	 * @return Schema
	 */
	public function object(
		array $properties,
		array $options = []
	): Schema {
		$normalized_props = [];

		foreach ($properties as $key => $value) {
			if ($value instanceof OA\Property) {
				$normalized_props[] = $value;
			} else {
				$normalized_props[] = $prop = new OA\Property([
					'property' => $key,
				]);

				// merge the value properties to the property
				foreach ($value as $prop_key => $prop_value) {
					$prop->{$prop_key} = $prop_value;
				}
			}
		}

		return new Schema([
			'type'       => 'object',
			'properties' => $normalized_props,
		] + $options);
	}

	/**
	 * Create schema with type `array`.
	 *
	 * @param null|Schema $item    The array items schema
	 * @param array       $options The schema options
	 *
	 * @return Schema
	 */
	public function array(?Schema $item = null, array $options = []): Schema
	{
		return new Schema([
			'type'  => 'array',
			'items' => $item ?? new OA\Items([]),
		] + $options);
	}

	/**
	 * Create schema with type `integer`.
	 *
	 * @param null|string $description the schema description
	 * @param array       $options     the schema options
	 *
	 * @return Schema
	 */
	public function integer(
		?string $description = null,
		array $options = []
	): Schema {
		return $this->type('integer', $description, $options);
	}

	/**
	 * Create schema with type `string`.
	 *
	 * @param null|string $description the schema description
	 * @param array       $options     the schema options
	 *
	 * @return Schema
	 */
	public function string(
		?string $description = null,
		array $options = []
	): Schema {
		return $this->type('string', $description, $options);
	}

	/**
	 * Create schema with type `boolean`.
	 *
	 * @param null|string $description the schema description
	 * @param array       $options     the schema options
	 *
	 * @return Schema
	 */
	public function boolean(
		?string $description = null,
		array $options = []
	): Schema {
		return $this->type('boolean', $description, $options);
	}

	/**
	 * Create schema with a given type.
	 *
	 * @param string|string[] $type        The schema type {@see Schema::$type}
	 * @param null|string     $description The schema description
	 * @param array           $options     The schema options
	 *
	 * @return Schema
	 */
	public function type(
		array|string $type,
		?string $description = null,
		array $options = []
	): Schema {
		$s = new Schema([
			'type' => $type,
		] + $options);

		if (null !== $description) {
			$s->description = $description;
		}

		return $s;
	}

	/**
	 * Extract api doc metadata from a table.
	 *
	 * @param Table $table The table
	 *
	 * @return array
	 */
	public function tableMeta(
		Table $table
	): array {
		$meta = $table->getMeta();

		$get = static function (string $key, $default) use ($meta) {
			$value = $meta->get($key);

			if (null === $value || '' === $value) {
				$value = $default;
			}

			return $value;
		};

		return [
			'singular_name' => $get('api.doc.singular_name', self::toHumanReadable($table->getSingularName())),
			'plural_name'   => $get('api.doc.plural_name', self::toHumanReadable($table->getPluralName())),
			'description'   => $get('api.doc.description', ''),
			'use_an'        => (bool) $get('api.doc.use_an', false),
		];
	}

	/**
	 * Push a value to an object property.
	 *
	 * @param object               $to        The object
	 * @param string               $prop      The property name
	 * @param mixed                $value     The value to push
	 * @param null|callable(mixed) $predicate The predicate to check if the value already exists
	 */
	public static function push(object $to, string $prop, mixed $value, ?callable $predicate = null): void
	{
		if (self::isUndefined($to->{$prop})) {
			$to->{$prop} = [];
		} elseif (!\is_array($to->{$prop})) {
			$to->{$prop} = [$to->{$prop}];
		}

		if ($predicate) {
			foreach ($to->{$prop} as $v) {
				if ($predicate($v)) {
					return;
				}
			}
		}

		$to->{$prop}[] = $value;
	}

	/**
	 * Try create an user friendly name.
	 *
	 * Examples:
	 *  - 'oz_user' => 'User'
	 *  - `user_profile` => `User Profile`
	 *  - `user_id` => `User Id`
	 *
	 * @param string $name the table name
	 */
	public static function toHumanReadable(string $name): string
	{
		$p = \explode('_', $name);

		$first = $p[0];

		if (\strlen($first) <= 2) {
			\array_shift($p);
		}

		return \implode(' ', \array_map(\ucfirst(...), $p));
	}

	/**
	 * Create a schema for a table entity.
	 *
	 * @param string|Table             $table   The table or the table name
	 * @param 'create'|'read'|'update' $for     Specify the entity schema usage
	 * @param array                    $options The schema options
	 *
	 * @return Schema
	 */
	protected function entitySchema(string|Table $table, string $for, array $options = []): Schema
	{
		$table        = \is_string($table) ? db()->getTableOrFail($table) : $table;
		$api_doc_meta = $this->tableMeta($table);
		$c_key        = \str_replace(' ', '', $api_doc_meta['singular_name'] . ' ' . \ucfirst($for));

		return $this->component('schemas', $c_key, function () use ($table, $for) {
			/** @var array<string, Schema> $properties */
			$properties = [];

			/** @var string[] $required_names */
			$required_names = [];

			$is_create = 'create' === $for;
			$is_update = 'update' === $for;

			foreach ($table->getColumns() as $column) {
				$type = $column->getType();

				if (($is_create || $is_update) && ($column->isPrivate() || $type->isAutoIncremented())) {
					continue;
				}

				if (!$this->include_private_column && $column->isPrivate()) {
					continue;
				}

				$name = $column->getFullName();

				$schema = $this->typeSchema($column->getType());

				if ($is_create && !$type->isNullAble() && null === $type->getDefault()) {
					$required_names[] = $name;
				}

				$properties[$name] = $schema;
			}

			return $this->object($properties, [
				'required' => $required_names,
			]);
		}, $options);
	}

	/**
	 * Converts a gobl type to a schema type.
	 *
	 * @param TypeInterface $type
	 *
	 * @return null|Schema
	 */
	protected function goblTypeToSchema(TypeInterface $type): ?Schema
	{
		$sc_type   = null;
		$t_name    = $type->getName();
		$t_default = $type->getDefault();

		switch ($t_name) {
			case TypeInt::NAME:
				$sc_type = $this->integer(null, [
					'format' => 'int32',
				]);

				break;

			case TypeString::NAME:
			case TypeEmail::NAME:
			case TypePassword::NAME:
				$sc_type = $this->string();

				/** @var TypeString $type */
				$one_of  = $type->getOption('one_of');
				$pattern = $type->getOption('pattern');

				if ($one_of) {
					$sc_type->enum = $one_of;
				}

				if ($pattern) {
					$sc_type->pattern = $pattern;
				}

				if (TypeEmail::NAME === $t_name || TypePassword::NAME === $t_name) {
					$sc_type->format = $t_name;
				}

				break;

			case TypeEnum::NAME:
				$options = [];
				$sc_type = $this->string(null, $options);

				/** @var TypeEnum $type */
				/** @var class-string<BackedEnum> $enum_class */
				$enum_class = $type->getOption('enum_class');

				if ($enum_class) {
					$sc_type->enum = $enum_class::cases();
				}

				break;

			case TypeBool::NAME:
				$sc_type   = $this->boolean();
				$t_default = null === $t_default ? null : (bool) $t_default;

				break;

			case TypeBigint::NAME:
				$sc_type = $this->string(null, [
					'format' => 'int64',
				]);

				break;

			case TypeFloat::NAME:
			case TypeDecimal::NAME:
				$sc_type = $this->type('number', null, [
					'format' => $type->getName(),
				]);

				break;

			case TypeList::NAME:
				$sc_type = $this->array();

				break;

			case TypeMap::NAME:
				$sc_type = $this->object([]);

				break;

			case TypeDate::NAME:
				/** @var TypeDate $type */
				$g_format = $type->getOption('format', TypeDate::FORMAT_TIMESTAMP);

				if (TypeDate::FORMAT_TIMESTAMP === $g_format) {
					$sc_type = $this->string('The UTC date in UNIX timestamp format.', [
						'format' => $type->isMicroseconds() ? 'float' : 'int64',
					]);
				} else {
					$sc_type = $this->string(\sprintf('The UTC date time in `%s` format.', $g_format), [
						'format' => 'date-time',
					]);
				}

				$t_default = null === $t_default ? null : $type->dbToPhp($t_default, db());

				break;
		}

		if (null === $sc_type) {
			return null;
		}

		if ($type->isNullable() && !\is_array($sc_type->type)) {
			$sc_type->type = ['null', $sc_type->type];
		}

		if ($type->hasDefault() && self::isUndefined($sc_type->default)) {
			$sc_type->default = $t_default;
		}

		return $sc_type;
	}

	/**
	 * Check if an annotation property value is {@see Generator::UNDEFINED}.
	 *
	 * @param $value
	 *
	 * @return bool
	 */
	protected static function isUndefined($value): bool
	{
		return Generator::UNDEFINED === $value;
	}
}
