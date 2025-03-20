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

use Gobl\DBAL\Table;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\DBAL\Types\TypeBool;
use Gobl\DBAL\Types\TypeDecimal;
use Gobl\DBAL\Types\TypeFloat;
use Gobl\DBAL\Types\TypeInt;
use Gobl\DBAL\Types\TypeList;
use Gobl\DBAL\Types\TypeMap;
use Gobl\DBAL\Types\TypeString;
use InvalidArgumentException;
use OpenApi\Annotations as OA;
use OpenApi\Annotations\Operation;
use OpenApi\Annotations\Response;
use OpenApi\Annotations\Schema;
use OpenApi\Generator;
use OZONE\Core\App\JSONResponse;
use OZONE\Core\Router\Route;

/**
 * Trait ApiDocManipulationTrait.
 */
trait ApiDocManipulationTrait
{
	/**
	 * @var array<string , OA\PathItem> The paths
	 */
	private array $paths                 = [];
	private bool $include_private_column = false;

	/**
	 * @var array<string, callable(TypeInterface):Schema> The gobl types to schema providers
	 */
	private static array $gobl_types_to_schema_providers = [];

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
	 * @param Route|string $route      The route or the route name to add
	 * @param string       $method     HTTP method
	 * @param string       $summary    Summary of the route
	 * @param array        $responses  OA\Response[]
	 * @param array        $properties Additional properties
	 *
	 * @return Operation
	 */
	public function addOperationFromRoute(
		Route|string $route,
		string $method,
		string $summary,
		array $responses,
		array $properties = []
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

		$path = $route->getPath();
		$op   = $this->addOperation($path, $method, $summary, $responses, $properties);

		$route_params             = [];
		$declared_params_patterns = $route->getDeclaredParams();

		foreach ($route->getPathParams() as $param) {
			$pattern        = $declared_params_patterns[$param] ?? Route::DEFAULT_PARAM_PATTERN;
			$route_params[] = new OA\PathParameter([
				'name'        => $param,
				'description' => \sprintf('The parameter `%s` should match the pattern `%s`.', $param, $pattern),
			]);
		}

		$this->path($path)->parameters = $route_params;

		return $op;
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
	public function addOperation(string $path, string $method, string $summary, array $responses, array $properties = []): Operation
	{
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
	 * @param int                                                                    $http_status_code the response HTTP status code
	 * @param string                                                                 $description      the response description
	 * @param array<string, OA\Attachable|OA\JsonContent|OA\MediaType|OA\XmlContent> $content          the response content
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
	 * @param array<string, OA\JsonContent|OA\MediaType|OA\XmlContent> $content
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
	 * @param string       $message          the message
	 * @param null|string  $description      the description
	 * @param int          $http_status_code the HTTP status code
	 *
	 * @return Response
	 */
	public function success(
		array|Schema $data,
		string $message = 'OK',
		?string $description = '',
		int $http_status_code = 200,
	): Response {
		return $this->response($http_status_code, $description, [
			'application/json' => $this->apiJson(JSONResponse::RESPONSE_CODE_SUCCESS, $message, $data),
		]);
	}

	/**
	 * Create an ozone error response.
	 *
	 * @param array|Schema $data             the data
	 * @param string       $message          the message
	 * @param null|string  $description      the description
	 * @param int          $http_status_code the HTTP status code
	 *
	 * @return Response
	 */
	public function error(
		array|Schema $data,
		string $message = 'OZ_ERROR_INTERNAL',
		?string $description = '',
		int $http_status_code = 200,
	): Response {
		return $this->response($http_status_code, $description, [
			'application/json' => $this->apiJson(JSONResponse::RESPONSE_CODE_ERROR, $message, $data),
		]);
	}

	/**
	 * Creates the API JSON response schema with a message and data.
	 *
	 * @param int          $ozone_error_code the error code: 0 for success and 1 for error
	 * @param string       $message          the message
	 * @param array|Schema $data             the data
	 */
	public function apiJson(int $ozone_error_code, string $message, array|Schema $data): OA\JsonContent
	{
		$data = $data instanceof Schema ? $data : $this->object($data);

		$utime = $this->integer('The response UNIX timestamp.');
		$stime = $this->integer('The response auth expiration UNIX timestamp.');

		return new OA\JsonContent([
			'schema' => $this->object([
				'error' => $this->integer(null, $ozone_error_code),
				'msg'   => $this->string(null, $message),
				'data'  => $data,
				'utime' => $utime,
				'stime' => $stime,
			]),
		]);
	}

	/**
	 * Create a schema for a table entity.
	 *
	 * @param string|Table $table         The table or the table name
	 * @param bool         $editable_only If true, only editable columns will be included
	 * @param bool         $for_update    If true, the schema will be for update
	 *
	 * @return Schema
	 */
	public function entitySchema(string|Table $table, bool $editable_only = false, bool $for_update = false): Schema
	{
		$table = \is_string($table) ? db()->getTableOrFail($table) : $table;

		/** @var array<string, Schema> $properties */
		$properties = [];

		/** @var string[] $required_names */
		$required_names = [];

		foreach ($table->getColumns() as $column) {
			$type = $column->getType();

			if ($editable_only && ($column->isPrivate() || $type->isAutoIncremented())) {
				continue;
			}

			if (!$this->include_private_column && $column->isPrivate()) {
				continue;
			}

			$name = $column->getFullName();

			$schema = $this->typeSchema($column->getType());

			if (!$for_update && !$type->isNullAble() && null === $type->getDefault()) {
				$required_names[] = $name;
			}

			$properties[$name] = $schema;
		}

		return new Schema([
			'type'       => 'object',
			'properties' => $properties,
			'required'   => $required_names,
		]);
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
			$schema_type = self::toSchemaType($type) ?? self::toSchemaType($type->getBaseType());

			$schema = new Schema([
				'type' => $type->isNullable() ? ['null', $schema_type] : $schema_type,
			]);
		}

		if (self::isUndefined($schema->example) && $type->hasDefault()) {
			$schema->example = $type->getDefault();
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
		return new Schema([
			'type'       => 'object',
			'properties' => $properties,
		] + $options);
	}

	/**
	 * Create schema with type `array`.
	 *
	 * @param Schema $item    The array items schema
	 * @param array  $options The schema options
	 *
	 * @return Schema
	 */
	public function array(Schema $item, array $options = []): Schema
	{
		return new Schema([
			'type'  => 'array',
			'items' => $item,
		] + $options);
	}

	/**
	 * Create schema with type `integer`.
	 *
	 * @param null|string $description the schema description
	 * @param null|int    $sample      the schema example
	 * @param array       $options     the schema options
	 *
	 * @return Schema
	 */
	public function integer(
		?string $description = null,
		?int $sample = null,
		array $options = []
	): Schema {
		return $this->type('integer', $description, $sample, $options);
	}

	/**
	 * Create schema with type `string`.
	 *
	 * @param null|string $description the schema description
	 * @param null|string $sample      the schema example
	 * @param array       $options     the schema options
	 *
	 * @return Schema
	 */
	public function string(
		?string $description = null,
		?string $sample = null,
		array $options = []
	): Schema {
		return $this->type('string', $description, $sample, $options);
	}

	/**
	 * Create schema with type `boolean`.
	 *
	 * @param null|string $description the schema description
	 * @param null|bool   $sample      the schema example
	 * @param array       $options     the schema options
	 *
	 * @return Schema
	 */
	public function boolean(
		?string $description = null,
		?bool $sample = null,
		array $options = []
	): Schema {
		return $this->type('boolean', $description, $sample, $options);
	}

	/**
	 * Create schema with a given type.
	 *
	 * @param string|string[] $type        The schema type {@see Schema::$type}
	 * @param null|string     $description The schema description
	 * @param null|mixed      $sample      The schema example
	 * @param array           $options     The schema options
	 *
	 * @return Schema
	 */
	public function type(
		array|string $type,
		?string $description = null,
		mixed $sample = null,
		array $options = []
	): Schema {
		$s = new Schema([
			'type' => $type,
		] + $options);

		if (null !== $sample) {
			$s->example = $sample;
		}
		if (null !== $description) {
			$s->description = $description;
		}

		return $s;
	}

	/**
	 * Create a paginated schema.
	 *
	 * @param Schema $item        The item schema
	 * @param string $items_name  The items name
	 * @param int    $default_max The default maximum number of items per page
	 *
	 * @return Schema
	 */
	public function paginated(Schema $item, string $items_name = 'items', int $default_max = 20): Schema
	{
		return $this->object([
			$items_name => $this->array($item),
			'page'      => $this->integer('The current page number.', 1),
			'max'       => $this->integer('The maximum number of items per page.', $default_max),
			'total'     => $this->integer('The total number of items.'),
		]);
	}

	/**
	 * Push a value to an object property.
	 *
	 * @param object $to    The object
	 * @param string $prop  The property name
	 * @param mixed  $value The value to push
	 */
	private static function push(object $to, string $prop, mixed $value): void
	{
		if (self::isUndefined($to->{$prop})) {
			$to->{$prop} = [];
		} elseif (!\is_array($to->{$prop})) {
			$to->{$prop} = [$to->{$prop}];
		}

		$to->{$prop}[] = $value;
	}

	/**
	 * Converts a gobl type to a schema type.
	 *
	 * @param TypeInterface $type
	 *
	 * @return null|string
	 */
	private static function toSchemaType(TypeInterface $type): ?string
	{
		return match ($type->getName()) {
			TypeInt::NAME    => 'integer',
			TypeString::NAME => 'string',
			TypeBool::NAME   => 'boolean',
			TypeFloat::NAME, TypeDecimal::NAME => 'number',
			TypeList::NAME => 'array',
			TypeMap::NAME  => 'object',
			default        => null,
		};
	}

	/**
	 * Check if the value is undefined.
	 *
	 * @param $value
	 *
	 * @return bool
	 */
	private static function isUndefined($value): bool
	{
		return Generator::UNDEFINED === $value;
	}
}
