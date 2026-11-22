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

namespace OZONE\Core\REST\ApiDoc;

use InvalidArgumentException;
use OpenApi\Annotations as OA;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Operation;
use OpenApi\Annotations\Response;
use OZONE\Core\Router\Enums\RouteFormDocPolicy;
use OZONE\Core\Router\ResolvedRouteOptions;
use OZONE\Core\Router\Route;
use OZONE\Core\Router\RoutePathParser;

/**
 * Class OperationBuilder.
 *
 * The paths and operations of the document. Operations built from a route carry its
 * security (`x-oz-security`), its form (`requestBody`, `x-oz-form`), its path parameters, and the
 * host it answers on when it declares one (`servers`).
 */
final class OperationBuilder
{
	/**
	 * @var array<string, OA\PathItem>
	 */
	private array $paths = [];

	public function __construct(
		private readonly OpenApi $openapi,
		private readonly SchemaBuilder $schemas,
		private readonly ParameterBuilder $parameters,
		private readonly ResponseBuilder $responses,
	) {}

	/**
	 * Creates new {@see OA\PathItem} or returns an existing.
	 */
	public function path(string $path): OA\PathItem
	{
		if (!isset($this->paths[$path])) {
			$p = new OA\PathItem([
				'path' => $path,
			]);
			$this->paths[$path] = $p;

			SchemaBuilder::push($this->openapi, 'paths', $p);
		}

		return $this->paths[$path];
	}

	/**
	 * Adds a new operation.
	 *
	 * @param string          $path       the request path
	 * @param string          $method     the request method
	 * @param string          $summary    the request summary
	 * @param array<Response> $responses  the request responses
	 * @param array           $properties the request properties
	 */
	public function add(
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
	 */
	public function fromRoute(
		Route|string $route,
		string $method,
		string $summary,
		array $responses,
		array $properties = [],
		array $path_params_values = []
	): Operation {
		if (\is_string($route)) {
			$route = context()->getRouter()->requireRoute($route);
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

			$route_path = $route->buildPath(context(), $path_params_values);
		}

		$op       = $this->add($route_path, $method, $summary, $responses, $properties);
		$resolved = $route->getOptions()->resolved();

		$this->documentSecurity($op, $resolved);
		$this->documentHost($op, $route);

		// Attach form documentation when not already overridden by the caller.
		if (SchemaBuilder::isUndefined($op->requestBody)) {
			$this->documentForm($op, $resolved);
		}

		$path_item                = $this->path($route_path);
		$declared_params_patterns = $route->getDeclaredParams();

		foreach ($route_params as $param) {
			if (isset($exclude_params[$param])) {
				continue;
			}

			$pattern  = $declared_params_patterns[$param] ?? Route::DEFAULT_PARAM_PATTERN;
			$oa_param = $this->parameters->parameter(
				$param,
				$this->schemas->string(null, [
					'pattern' => $pattern,
				]),
				\sprintf('The parameter `%s` should match the pattern `%s`.', $param, $pattern)
			);

			SchemaBuilder::push(
				$path_item,
				'parameters',
				$oa_param,
				// Operations can share a path item: add the parameter once.
				static fn ($a) => $a->name === $param
			);
		}

		return $op;
	}

	/**
	 * Push an extension to an OpenAPI annotation, initializing the `x` property if needed.
	 *
	 * @param Operation $op    the OpenAPI operation
	 * @param string    $name  the extension name
	 * @param array     $value the extension value
	 */
	public static function pushExtension(Operation $op, string $name, array $value): void
	{
		$extension = [
			$name => [
				'name'  => $name,
				'value' => $value,
			],
		];

		if (SchemaBuilder::isUndefined($op->x)) {
			$op->x = $extension;
		} elseif (\is_array($op->x)) {
			$op->x = \array_merge($op->x, $extension);
		}
	}

	/**
	 * Adds the operation's own `servers` entry for a route that declares a host: the URL of the
	 * document's request (scheme, port and base path) on the route's host template, each host
	 * parameter a server variable of the same `{name}`.
	 *
	 * A server variable has no pattern, so each one's description gives the parameter's. Its required
	 * `default` is the value of the host serving the document when the route matches that host, so
	 * trying an operation stays where the reader is; otherwise it is the parameter's name, a
	 * placeholder to replace, which the description says. No `enum` is given: a pattern does not
	 * enumerate its values. An optional part of the template is shown present.
	 */
	private function documentHost(Operation $op, Route $route): void
	{
		$template = $route->getHost();

		if (null === $template) {
			return;
		}

		$context      = context();
		$served       = $route->matchHost($context->getRequest()->getUri()->getHost()) ?? [];
		$declared     = $route->getDeclaredParams();
		$placeholders = [];
		$variables    = [];

		foreach ($route->getHostParams() as $name) {
			$value               = $served[$name] ?? '';
			$placeholders[$name] = '{' . $name . '}';
			$variables[]         = new OA\ServerVariable([
				'serverVariable' => $name,
				'default'        => '' === $value ? $name : $value,
				'description'    => \sprintf(
					'The host parameter `%s`, matching the pattern `%s`. %s',
					$name,
					$declared[$name] ?? Route::DEFAULT_HOST_PARAM_PATTERN,
					'' === $value
						? 'The default is a placeholder to replace: this document is not served on a host of the route.'
						: 'The default is the value of the host serving this document.'
				),
			]);
		}

		$host = new RoutePathParser($template, $context->getRouter(), Route::DEFAULT_HOST_PARAM_PATTERN)
			->buildPath($context, $placeholders);

		$server = [
			'url'         => $context->buildUri('/')->withHost($host)->getBaseUrl(),
			'description' => \sprintf('The host the route answers on: `%s`.', $template),
		];

		if (!empty($variables)) {
			$server['variables'] = $variables;
		}

		$op->servers = [new OA\Server($server)];
	}

	/**
	 * Adds the `x-oz-security` extension: the route's authentication methods and guards.
	 */
	private function documentSecurity(Operation $op, ResolvedRouteOptions $resolved): void
	{
		$auth_methods      = $resolved->authentication_methods;
		$guard_descriptors = $resolved->guard_descriptors;

		if (empty($auth_methods) && empty($guard_descriptors)) {
			return;
		}

		self::pushExtension($op, 'oz-security', [
			'auth_methods' => \array_map(
				static fn ($m) => \basename(\str_replace('\\', '/', $m)),
				$auth_methods
			),
			'guards'       => $guard_descriptors,
		]);
	}

	/**
	 * Adds the request body of a statically documentable form, and the `x-oz-form` extension.
	 */
	private function documentForm(Operation $op, ResolvedRouteOptions $resolved): void
	{
		$doc_policy = $resolved->docPolicy();

		if (null === $doc_policy) {
			return;
		}

		$provider_class = $resolved->providerClass();
		$is_resumable   = $resolved->hasResumeSupport();

		// require_real_context: true when the client must go through this route's URL and cannot
		// use the standalone resumable-form endpoints.
		$require_real_context = null === $provider_class || $provider_class::requiresRealContext();

		$extension = [
			'policy'               => $doc_policy->value,
			'resumable'            => $is_resumable,
			'require_real_context' => $require_real_context,
		];

		if (RouteFormDocPolicy::STATIC === $doc_policy) {
			$static_bundle = $resolved->staticFormBundle();

			if (null !== $static_bundle) {
				$op->requestBody = $this->responses->requestBodyFromForm($static_bundle);
			}

			self::pushExtension($op, 'oz-form', $extension);

			return;
		}

		if (RouteFormDocPolicy::DYNAMIC === $doc_policy) {
			$extension['init_form'] = null !== $provider_class
				? $provider_class::initForm()?->toClientArray()
				: $resolved->formDeclaration()?->getDocPreviewForm()?->toClientArray();
		} else {
			$extension['init_form'] = null;
		}

		if ($is_resumable && !$require_real_context && null !== $provider_class) {
			$extension['provider_name'] = $provider_class::getName();
		}

		self::pushExtension($op, 'oz-form', $extension);
	}
}
