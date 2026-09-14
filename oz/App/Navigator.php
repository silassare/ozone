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

namespace OZONE\Core\App;

use InvalidArgumentException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Hooks\Events\RedirectHook;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Http\Request;
use OZONE\Core\Http\Response;
use OZONE\Core\Http\Uri;
use OZONE\Core\OZone;
use OZONE\Core\Web\Views\RedirectView;

/**
 * Class Navigator.
 *
 * Sub-requests and redirections of a context. A request and its sub-requests share one
 * redirection history, owned by the root context of the request, which catches loops; each
 * request starts with an empty one.
 *
 * @internal use the {@see Context} methods
 */
final class Navigator
{
	/**
	 * Redirection targets of this request tree (used on the root navigator only).
	 *
	 * @var array<string, array>
	 */
	private array $history = [];

	/**
	 * Routes run in this request tree (used on the root navigator only).
	 *
	 * @var list<array{name: string, path: string}>
	 */
	private array $routes_run = [];

	/**
	 * Navigator constructor.
	 */
	public function __construct(private readonly Context $context, private readonly HTTPEnvironment $env) {}

	/**
	 * Makes a sub-request to a route.
	 *
	 * @param string $route_name        the route name
	 * @param array  $params            the route parameters
	 * @param array  $query             the query parameters
	 * @param bool   $override_response whether the sub-response replaces the current one
	 */
	public function callRoute(
		string $route_name,
		array $params = [],
		array $query = [],
		bool $override_response = true
	): Response {
		$path = $this->context->getRouter()->buildRoutePath($this->context, $route_name, $params);

		return $this->callPath($path, $params, $query, $override_response);
	}

	/**
	 * Makes a sub-request to a path.
	 *
	 * @param string $path              the path
	 * @param array  $attributes        the request attributes
	 * @param array  $query             the query parameters
	 * @param bool   $override_response whether the sub-response replaces the current one
	 */
	public function callPath(
		string $path,
		array $attributes = [],
		array $query = [],
		bool $override_response = true
	): Response {
		$request = Request::createFromHTTPEnvironment($this->env);
		$uri     = $request->getUri()
			->withPath($path, true)
			->withQueryArray($query);

		$request = $request->withAttributes($attributes)
			->withUri($uri);

		$sub      = new Context($this->env, $request, $this->context, $this->context->isApiContext());
		$response = $sub->handle()->getResponse();

		if ($override_response) {
			$this->context->setResponse($response);
		}

		return $response;
	}

	/**
	 * Redirects the client to a URL.
	 *
	 * @param string|Uri $to     the destination; a path keeps the request's scheme and host
	 * @param null|int   $status the redirection HTTP status code
	 */
	public function redirect(string|Uri $to, ?int $status = null): never
	{
		$uri = $to instanceof Uri ? $to : Uri::createFromString($to);

		if (empty($uri->getHost())) {
			$uri = $this->context->getRequest()->getUri()
				->withPath($uri->getPath())
				->withFragment($uri->getFragment())
				->withQuery($uri->getQuery());
		}

		$uri_str = (string) $uri;

		$this->track($uri_str, ['status' => $status]);

		if (!\filter_var($uri_str, \FILTER_VALIDATE_URL)) {
			throw new InvalidArgumentException(\sprintf('Invalid redirect url: %s', $uri_str));
		}

		(new RedirectHook($this->context, $uri))->dispatch();

		if ($this->context->isApiContext()) {
			$this->context->respond($this->context->getResponse()->withRedirect($uri_str, $status));
		}

		$this->redirectRoute(RedirectView::REDIRECT_ROUTE, ['url' => $uri_str, 'status' => $status]);
	}

	/**
	 * Redirects to a route: the client is sent to it, or, for an internal route or when
	 * `$inform_user` is false, it is served through a sub-request.
	 *
	 * @param string   $route_name  the route name
	 * @param array    $params      the route parameters
	 * @param array    $query       the query parameters
	 * @param bool     $inform_user whether the client is redirected
	 * @param null|int $status      the redirection HTTP status code
	 */
	public function redirectRoute(
		string $route_name,
		array $params = [],
		array $query = [],
		bool $inform_user = true,
		?int $status = null,
	): never {
		$this->track($route_name, ['params' => $params, 'query' => $query, 'inform_user' => $inform_user]);

		$path = $this->context->getRouter()->buildRoutePath($this->context, $route_name, $params);

		$this->root()->history[$route_name] = ['path' => $path, 'params' => $params];

		if ($inform_user && !OZone::isInternalPath($path)) {
			$uri = Uri::createFromEnvironment($this->env)
				->withPath($path, true)
				->withQueryArray($query);

			$this->redirect((string) $uri, $status);
		}

		$this->callPath($path, $params, $query);
		$this->context->respond();
	}

	/**
	 * The navigator of the root context of this request tree.
	 */
	/**
	 * Records a route run in this request tree, and returns the whole trail.
	 *
	 * Owned by the root context like the redirection history, so it dies with the request. It used
	 * to be a `static` inside `Router::runRoute()`, which never reset: a persistent worker threw
	 * "Possible recursive redirection" on its eleventh route run, whatever the request.
	 *
	 * @param string $name the route name
	 * @param string $path the route path
	 *
	 * @return list<array{name: string, path: string}>
	 *
	 * @internal
	 */
	public function recordRouteRun(string $name, string $path): array
	{
		$root               = $this->root();
		$root->routes_run[] = ['name' => $name, 'path' => $path];

		return $root->routes_run;
	}

	/**
	 * Records a redirection target, refusing one already reached in this request tree.
	 *
	 * @throws RuntimeException on a redirection loop
	 */
	private function track(string $target, array $info): void
	{
		$root = $this->root();

		if (isset($root->history[$target])) {
			throw new RuntimeException('OZ_RECURSIVE_REDIRECTION', [
				'to'      => $target,
				'data'    => $info,
				'history' => $root->history,
			]);
		}

		$root->history[$target] = $info;
	}

	private function root(): self
	{
		$context = $this->context;

		while (null !== ($parent = $context->getParent())) {
			$context = $parent;
		}

		return $context->navigator();
	}
}
