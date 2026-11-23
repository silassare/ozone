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
use OZONE\Core\Exceptions\BaseException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Hooks\Events\RedirectHook;
use OZONE\Core\Http\Body;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Http\RedirectPolicy;
use OZONE\Core\Http\Request;
use OZONE\Core\Http\Response;
use OZONE\Core\Http\Uri;
use OZONE\Core\OZone;
use OZONE\Core\Router\Route;
use OZONE\Core\Runtime\Exceptions\RequestFinished;
use OZONE\Core\Runtime\Interfaces\ResponseSinkInterface;
use OZONE\Core\Runtime\ResponseCapture;
use OZONE\Core\Scopes\Interfaces\ScopeInterface;
use OZONE\Core\Scopes\ScopeKind;
use OZONE\Core\Utils\Utils;
use OZONE\Core\Web\Views\RedirectView;

/**
 * Class Navigator.
 *
 * Sub-requests and redirections of a context. A request and its sub-requests share one
 * redirection history, owned by the root context of the request, which catches loops; each
 * request starts with an empty one.
 *
 * @internal use the {@see Context} methods
 *
 * @psalm-type AnonymousCallKey = array{
 *     scope: string,
 *     route: string,
 *     params: array,
 *     query: array,
 *     language: null|string,
 * }
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
	 * Calls a route as an anonymous visitor would, in this process, and answers what the visitor
	 * would be answered, unsent.
	 *
	 * Not a sub-request ({@see self::callRoute()}, which keeps the visitor's identity, body and
	 * language): a request of its own, made from the route's name and nothing else. A `GET` from
	 * `127.0.0.1`, to the scope's `OZ_DEFAULT_ORIGIN`, with no cookie, no `Authorization`, no
	 * `Origin`, no forwarding header and an empty body; `Accept: application/json` for an API scope;
	 * `Accept-Language` only when a language is given. It is a root of its own: its own redirection
	 * history and route trail, no authentication of this request's, internal routes refused, the
	 * scope's maintenance, rate limits and `RequestHook`, `RouteFound` and `RouteBeforeRun` as for any
	 * visitor. What comes back is the whole visitor's response, `ResponseHook` headers included,
	 * an error or a redirection answered as a status like any other; no session is written,
	 * `FinishHook` and `EndRequestHook` are not dispatched for it, and the process goes on whatever
	 * the runtime. In another scope it runs with that scope in use ({@see OZone::inScope()}).
	 *
	 * The work is the scope, the route, its params, the query and the language. The same call made
	 * again inside itself is refused as a cycle, naming the calls in flight: a GET as nobody with the
	 * same inputs answers the same, so it can only recurse. Calls that differ are recursion with
	 * less of the work each time, and are bounded by `OZ_ANONYMOUS_CALL_MAX_DEPTH` (`oz.request`).
	 *
	 * @param string      $route_name the route name: a GET, by name only
	 * @param array       $params     the route parameters
	 * @param array       $query      the query parameters
	 * @param null|string $scope      the scope of the route, null for the one in use
	 * @param null|string $language   the language asked for (`Accept-Language`), null for none
	 *
	 * @throws RuntimeException inside an open transaction, on a cycle or past the depth bound, and
	 *                          when the scope cannot be used by this process
	 */
	public function callAnonymously(
		string $route_name,
		array $params = [],
		array $query = [],
		?string $scope = null,
		?string $language = null
	): Response {
		// The rows an open transaction wrote are this connection's alone: the call would read what no
		// visitor can see, and answer what no visitor would be answered.
		if (Db::inTransaction()) {
			throw new RuntimeException(
				'A route cannot be called as nobody inside an open transaction: it would read rows no'
					. ' visitor can see.',
				['route' => $route_name]
			);
		}

		$target = null === $scope ? scope() : app()->getScope($scope);
		$key    = [
			'scope'    => $target->getName(),
			'route'    => $route_name,
			'params'   => $params,
			'query'    => $query,
			'language' => $language,
		];
		$chain = self::callsInFlight($this->context);

		if (\in_array($key, $chain, true)) {
			throw new RuntimeException(\sprintf(
				'A route called as nobody calls itself with the same inputs, which never ends: %s.',
				self::describeCalls([...$chain, $key])
			));
		}

		$max = (int) Settings::get('oz.request', 'OZ_ANONYMOUS_CALL_MAX_DEPTH');

		if (\count($chain) >= $max) {
			throw new RuntimeException(\sprintf(
				'Routes called as nobody are nested deeper than OZ_ANONYMOUS_CALL_MAX_DEPTH (%d): %s.',
				$max,
				self::describeCalls([...$chain, $key])
			));
		}

		return OZone::inScope($target, fn (): Response => $this->answerAnonymously($target, $key));
	}

	/**
	 * Redirects the client to a URL.
	 *
	 * The destination must name a host `OZ_REDIRECT_ALLOWED_HOSTS` allows, the current request's own
	 * being allowed whatever that setting says ({@see RedirectPolicy}). A destination a caller took
	 * from a request is how an open redirect is built, and a caller cannot be relied on to have
	 * checked it, so the check is here rather than at each of them.
	 *
	 * @param string|Uri $to     the destination; a path keeps the request's scheme and host
	 * @param null|int   $status the redirection HTTP status code
	 *
	 * @throws RuntimeException when the destination names a host this server may not send a client to
	 */
	public function redirect(string|Uri $to, ?int $status = null): never
	{
		$this->redirectTo($to, $status, null);
	}

	/**
	 * Redirects to a route: the client is sent to it, or, for an internal route or when
	 * `$inform_user` is false, it is served through a sub-request.
	 *
	 * A route on a host other than the one being served is sent to on that host, which needs no
	 * entry in `OZ_REDIRECT_ALLOWED_HOSTS`: it is built from the route's own host template, declared
	 * in code ({@see self::namesRouteHost()}). A sub-request stays on the host served.
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

		$router = $this->context->getRouter();
		$path   = $router->buildRoutePath($this->context, $route_name, $params);

		$this->root()->history[$route_name] = ['path' => $path, 'params' => $params];

		if ($inform_user && !OZone::isInternalPath($path)) {
			$uri  = Uri::createFromEnvironment($this->env)
				->withPath($path, true)
				->withQueryArray($query);
			$host = $router->buildRouteHost($this->context, $route_name, $params);

			if (null !== $host) {
				$uri = $uri->withHost($host);
			}

			$this->redirectTo((string) $uri, $status, $router->requireRoute($route_name));
		}

		$this->callPath($path, $params, $query);
		$this->context->respond();
	}

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
	 * Redirects the client to a URL, as {@see redirect()} does: a URL built for a route that declares
	 * a host may name that host whatever `OZ_REDIRECT_ALLOWED_HOSTS` says.
	 *
	 * @param null|Route $route the route the URL was built for, null for a URL from anywhere else
	 *
	 * @throws RuntimeException when the destination names a host this server may not send a client to
	 */
	private function redirectTo(string|Uri $to, ?int $status, ?Route $route): never
	{
		$uri = $to instanceof Uri ? $to : Uri::createFromString($to);

		if ('' === $uri->getHost()) {
			$uri = $this->context->getRequest()->getUri()
				->withPath($uri->getPath())
				->withFragment($uri->getFragment())
				->withQuery($uri->getQuery());
		}

		$uri_str = (string) $uri;

		$this->track($uri_str, ['status' => $status]);

		if (!Uri::isStrictUrl($uri_str)) {
			throw new InvalidArgumentException(\sprintf('Invalid redirect url: %s', $uri_str));
		}

		if (!self::namesRouteHost($uri, $route) && !RedirectPolicy::fromContext($this->context)->allows($uri)) {
			throw new RuntimeException('OZ_REDIRECT_HOST_NOT_ALLOWED', [
				'to'   => $uri_str,
				'host' => $uri->getHost(),
			]);
		}

		new RedirectHook($this->context, $uri)->dispatch();

		if ($this->context->isApiContext()) {
			$this->context->respond($this->context->getResponse()->withRedirect($uri_str, $status));
		}

		$this->redirectRoute(RedirectView::REDIRECT_ROUTE, ['url' => $uri_str, 'status' => $status]);
	}

	/**
	 * Answers a route called as nobody, with its scope in use: {@see self::callAnonymously()}.
	 *
	 * @param AnonymousCallKey $key
	 */
	private function answerAnonymously(ScopeInterface $scope, array $key): Response
	{
		$is_api    = ScopeKind::API === ScopeKind::of($scope);
		$origin    = (string) Settings::get('oz.request', 'OZ_DEFAULT_ORIGIN');
		$ob_level  = \ob_get_level();
		$tree      = Context::detachTree();
		$reporting = BaseException::enterRequest();

		try {
			// A context for the path alone, first: a route's path may take a value from the request
			// it is built for (the `{lang}` of a public page), and that request is the visitor's.
			$probe = self::visitorContext($origin, '/', [], $is_api, $key['language'], null);
			$path  = $probe->getRouter()->buildRoutePath($probe, $key['route'], $key['params']);

			Context::release();

			$capture = new ResponseCapture();
			$visitor = self::visitorContext($origin, $path, $key['query'], $is_api, $key['language'], $capture)
				->asAnonymousCallOf($this->context, $key);

			try {
				$visitor->handle()
					->respond();
			} catch (RequestFinished) {
				// Answered: into the capture.
			}

			return $capture->response() ?? throw new RuntimeException(\sprintf(
				'The route "%s" called as nobody was not answered.',
				$key['route']
			));
		} finally {
			// What the call left open is its own, never what the caller had opened before it.
			Utils::closeOutputBuffers($ob_level, false);
			BaseException::leaveRequest($reporting);
			Context::restoreTree($tree);
		}
	}

	/**
	 * The root context of a visitor's GET, from 127.0.0.1, carrying nothing but what it is given.
	 */
	private static function visitorContext(
		string $origin,
		string $path,
		array $query,
		bool $is_api,
		?string $language,
		?ResponseSinkInterface $sink
	): Context {
		$uri = Uri::createFromString($origin)
			->withPath($path)
			->withQueryArray($query);

		$headers = [];

		if ($is_api) {
			$headers['Accept'] = 'application/json';
		}

		if (null !== $language) {
			$headers['Accept-Language'] = $language;
		}

		$env     = HTTPEnvironment::fromParts('GET', (string) $uri, $headers, ['REMOTE_ADDR' => '127.0.0.1']);
		$request = Request::createFromHTTPEnvironment($env, Body::fromString(''));

		return new Context($env, $request, null, $is_api, $sink);
	}

	/**
	 * The routes called as nobody that the given context is answering, from the innermost out: each
	 * call's root knows the call and the context that made it.
	 *
	 * @return list<AnonymousCallKey>
	 */
	private static function callsInFlight(Context $context): array
	{
		$chain = [];
		$from  = $context;

		while (null !== $from) {
			$root = $from;

			while (null !== ($parent = $root->getParent())) {
				$root = $parent;
			}

			$call = $root->anonymousCall();

			if (null === $call) {
				break;
			}

			$chain[] = $call['key'];
			$from    = $call['caller'];
		}

		return $chain;
	}

	/**
	 * Calls in flight as an error names them, the first one made first.
	 *
	 * @param list<AnonymousCallKey> $chain the calls, innermost first, then the one being made
	 */
	private static function describeCalls(array $chain): string
	{
		$made = \array_pop($chain);
		$list = [...\array_reverse($chain), $made];

		return \implode(' -> ', \array_map(static function (array $key): string {
			$inputs = \array_filter([
				'params'   => $key['params'],
				'query'    => $key['query'],
				'language' => $key['language'],
			], static fn (mixed $value): bool => null !== $value && [] !== $value);

			return $key['route'] . ' in ' . $key['scope']
				. ([] === $inputs ? '' : ' ' . \json_encode($inputs, \JSON_UNESCAPED_SLASHES));
		}, $list));
	}

	/**
	 * Whether a redirection target names the host of the route it was built for.
	 *
	 * That host is filled into the route's host template, which the project declared in code, and
	 * {@see Route::buildHost()} refuses a value the template does not match: it is allowed by
	 * construction, as wide as the template and its params' patterns, and never chosen by a request.
	 */
	private static function namesRouteHost(Uri $uri, ?Route $route): bool
	{
		return null !== $route && null !== $route->getHost() && null !== $route->matchHost($uri->getHost());
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

	/**
	 * The navigator of the root context of this request tree.
	 */
	private function root(): self
	{
		$context = $this->context;

		while (null !== ($parent = $context->getParent())) {
			$context = $parent;
		}

		return $context->navigator();
	}
}
