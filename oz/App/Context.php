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

use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodInterface;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodStatefulInterface;
use OZONE\Core\Auth\StatefulAuthenticationMethodStore;
use OZONE\Core\Exceptions\BaseException;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Hooks\Events\FinishHook;
use OZONE\Core\Hooks\Events\RequestHook;
use OZONE\Core\Hooks\Events\ResponseHook;
use OZONE\Core\Http\ClientInfo;
use OZONE\Core\Http\Headers;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Http\Request;
use OZONE\Core\Http\Response;
use OZONE\Core\Http\ResponseEmitter;
use OZONE\Core\Http\Uri;
use OZONE\Core\OZone;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Runtime\Exceptions\RequestFinished;
use OZONE\Core\Runtime\Interfaces\ResponseSinkInterface;
use OZONE\Core\Runtime\Runtime;
use PHPUtils\Store\StoreNotEditable;
use Throwable;

/**
 * Class Context.
 *
 * One request (or sub-request) and everything about it: the request and response, the
 * matched route, the context tree (root, current, parent) and the router. Its other
 * concerns live in dedicated objects, which the shortcut methods below delegate to:
 *
 * - {@see self::client()}: client IP, host, origin ({@see ClientInfo});
 * - {@see self::authState()}: the authentication method, stateful store and users
 *   ({@see ContextAuth});
 * - sub-requests and redirections ({@see Navigator});
 * - sending the response ({@see ResponseEmitter}, or the worker's {@see ResponseSinkInterface}).
 */
final class Context
{
	public const CONTEXT_TYPE_API = 1;

	public const CONTEXT_TYPE_WEB = 2;

	private bool $handle_called = false;

	private bool $is_sub_request;

	private int $context_type;

	private ?Router $t_router = null;

	private HTTPEnvironment $http_environment;

	private Request $request;

	private Response $response;

	private ContextAuth $auth_state;

	private ?ClientInfo $client = null;

	private ?Navigator $navigator = null;

	private ?RouteInfo $route_info = null;

	private static ?self $root_context    = null;
	private static ?self $current_context = null;

	/**
	 * Context constructor.
	 *
	 * @param HTTPEnvironment            $http_env
	 * @param null|Request               $request
	 * @param null|Context               $parent
	 * @param bool                       $is_api
	 * @param null|ResponseSinkInterface $response_sink where the response goes instead of PHP's
	 *                                                  output; a root context's only, see
	 *                                                  {@see self::getResponseSink()}
	 */
	public function __construct(
		HTTPEnvironment $http_env,
		?Request $request = null,
		private readonly ?self $parent = null,
		bool $is_api = true,
		private readonly ?ResponseSinkInterface $response_sink = null
	) {
		if (null === self::$root_context) {
			self::$root_context = $this;
		} elseif (null === $parent) {
			throw new RuntimeException('Parent context is required. Root context already set.');
		}

		$this->is_sub_request = null !== $this->parent;
		$this->context_type   = $is_api ? self::CONTEXT_TYPE_API : self::CONTEXT_TYPE_WEB;

		$this->http_environment = $http_env;
		$this->request          = $request ?? Request::createFromHTTPEnvironment($http_env);
		$headers                = new Headers(['Content-Type' => 'text/html; charset=UTF-8']);
		$response               = new Response(200, $headers);
		$this->response         = $response->withProtocolVersion($this->request->getProtocolVersion());

		$this->auth_state = new ContextAuth(new AuthUsers($this));
	}

	/**
	 * Disable clone.
	 */
	private function __clone() {}

	/**
	 * Ends the current request's context tree, so the next request can start its own.
	 *
	 * A root context is refused while one is already set, and the request lifecycle normally ends in
	 * `exit`, so one process serves one request. A persistent worker (RoadRunner, Swoole,
	 * FrankenPHP worker mode) has to call this between requests instead -- together with
	 * {@see OZone::endRequest()}, which releases the rest of the per-request state.
	 *
	 * @internal
	 */
	public static function release(): void
	{
		self::$root_context    = null;
		self::$current_context = null;
	}

	/**
	 * Returns the current context.
	 */
	public static function current(): static
	{
		if (!isset(self::$current_context)) {
			if (!isset(self::$root_context)) {
				throw new RuntimeException('No context was created.');
			}

			return self::$root_context;
		}

		return self::$current_context;
	}

	/**
	 * Whether a request's context tree exists (between two requests of a worker, it does not).
	 */
	public static function hasRoot(): bool
	{
		return null !== self::$root_context;
	}

	/**
	 * Gets the root context.
	 */
	public static function root(): static
	{
		if (!isset(self::$root_context)) {
			throw new RuntimeException('No context was created.');
		}

		return self::$root_context;
	}

	/**
	 * Checks whether json response should be returned.
	 *
	 * @return bool
	 */
	public function shouldReturnJSON(): bool
	{
		$accept = $this->request->getHeaderLine('HTTP_ACCEPT');

		// in api context, we always return json
		// if the request accept contains application/json
		if ($this->isApiContext()) {
			return \is_int(\strpos($accept, 'application/json'));
		}

		// in web context, we return json
		// if the request accept does not contains text/html
		// and contains application/json
		return !\is_int(\strpos($accept, 'text/html')) && \is_int(\strpos($accept, 'application/json'));
	}

	/**
	 * Handle the incoming request.
	 *
	 * @return $this
	 */
	public function handle(): static
	{
		if ($this->handle_called) {
			throw new RuntimeException('The request is already handled.');
		}

		$this->handle_called = true;

		$previous              = self::$current_context;
		self::$current_context = $this;

		try {
			$uri           = $this->request->getUri();
			$internal_path = OZone::isInternalPath($uri->getPath());

			// prevent request to any internal route path
			// this is allowed only in sub-request
			if ($internal_path && !$this->is_sub_request) {
				throw new ForbiddenException();
			}

			(new RequestHook($this))->dispatch();

			$this->getRouter()
				->handle($this, function (RouteInfo $route_info): void {
					$this->authenticate($route_info);
				});
		} catch (RequestFinished $finished) {
			// Control flow, not an error: a persistent runtime uses it where PHP-FPM would `exit`,
			// so it has to reach the worker loop rather than become a response.
			throw $finished;
		} catch (Throwable $t) {
			BaseException::tryConvert($t)
				->informClient($this);
		} finally {
			self::$current_context = $previous;
		}

		return $this;
	}

	/**
	 * Gets HTTP environment.
	 *
	 * @return StoreNotEditable
	 */
	public function getHTTPEnvironment(): StoreNotEditable
	{
		return new StoreNotEditable($this->http_environment->all());
	}

	/**
	 * Gets request instance object.
	 *
	 * @return Request
	 */
	public function getRequest(): Request
	{
		return $this->request;
	}

	/**
	 * Gets route info instance object.
	 *
	 * @return RouteInfo
	 */
	public function getRouteInfo(): RouteInfo
	{
		if (!isset($this->route_info)) {
			throw new RuntimeException('No route info yet: the router has not matched a route.');
		}

		return $this->route_info;
	}

	/**
	 * Gets response.
	 *
	 * @return Response
	 */
	public function getResponse(): Response
	{
		return $this->response;
	}

	/**
	 * Sets response.
	 *
	 * @param Response $response
	 */
	public function setResponse(Response $response): static
	{
		$this->response = $response;

		return $this;
	}

	/**
	 * Gets the parent context of a sub-request, or null for a request.
	 */
	public function getParent(): ?self
	{
		return $this->parent;
	}

	/**
	 * What the request tells about its client: IP, host, origin.
	 */
	public function client(): ClientInfo
	{
		return $this->client ??= new ClientInfo($this->http_environment, $this->request);
	}

	/**
	 * The authentication state of the request.
	 */
	public function authState(): ContextAuth
	{
		return $this->auth_state;
	}

	/**
	 * The navigator handling sub-requests and redirections.
	 *
	 * @internal use {@see self::callRoute()}, {@see self::redirect()}, ...
	 */
	public function navigator(): Navigator
	{
		return $this->navigator ??= new Navigator($this, $this->http_environment);
	}

	/**
	 * Gets current auth.
	 *
	 * Shortcut for {@see ContextAuth::method()}.
	 *
	 * @return AuthenticationMethodInterface
	 */
	public function auth(): AuthenticationMethodInterface
	{
		return $this->auth_state->method();
	}

	/**
	 * Checks if we have an authenticated user.
	 *
	 * @return bool true when user is authenticated, false otherwise
	 */
	public function hasAuthenticatedUser(): bool
	{
		return $this->auth_state->hasAuthenticatedUser();
	}

	/**
	 * Checks if we have a stateful auth method.
	 *
	 * @return bool
	 */
	public function hasStatefulAuth(): bool
	{
		return $this->auth_state->isStateful();
	}

	/**
	 * Gets {@link AuthenticationMethodStatefulInterface} instance object.
	 *
	 * @return AuthenticationMethodStatefulInterface
	 */
	public function requireStatefulAuth(): AuthenticationMethodStatefulInterface
	{
		return $this->auth_state->requireStateful();
	}

	/**
	 * Try to get the state if the auth method is stateful.
	 *
	 * @return null|StatefulAuthenticationMethodStore
	 */
	public function authStore(): ?StatefulAuthenticationMethodStore
	{
		return $this->auth_state->store();
	}

	/**
	 * Make sure we have a stateful auth method and return its store.
	 *
	 * @return StatefulAuthenticationMethodStore
	 */
	public function requireAuthStore(): StatefulAuthenticationMethodStore
	{
		return $this->auth_state->requireStore();
	}

	/**
	 * Gets attached {@link AuthUsers} instance object.
	 *
	 * @return AuthUsers
	 */
	public function getAuthUsers(): AuthUsers
	{
		return $this->auth_state->users();
	}

	/**
	 * Gets the router instance object.
	 *
	 * @return Router
	 */
	public function getRouter(): Router
	{
		if (!isset($this->t_router)) {
			$this->t_router = $this->isApiContext() ? OZone::getApiRouter() : OZone::getWebRouter();
		}

		return $this->t_router;
	}

	/**
	 * Checks if we are in WebSite context.
	 *
	 * @return bool
	 */
	public function isWebContext(): bool
	{
		return self::CONTEXT_TYPE_WEB === $this->context_type;
	}

	/**
	 * Checks if we are in API context.
	 *
	 * @return bool
	 */
	public function isApiContext(): bool
	{
		return self::CONTEXT_TYPE_API === $this->context_type;
	}

	/**
	 * Checks it is a sub-request.
	 *
	 * @return bool
	 */
	public function isSubRequest(): bool
	{
		return $this->is_sub_request;
	}

	/**
	 * Gets base url.
	 *
	 * @return string
	 */
	public function getBaseUrl(): string
	{
		return $this->buildUri('/')
			->getBaseUrl();
	}

	/**
	 * Builds URI with a given path and query data.
	 *
	 * @param string $path
	 * @param array  $query
	 *
	 * @return Uri
	 */
	public function buildUri(string $path, array $query = []): Uri
	{
		return $this->request->getUri()
			->withHost($this->client()->host())
			->withPath($path, true)
			->withQueryArray($query);
	}

	/**
	 * Builds URI with a given path and query data.
	 *
	 * @param string $name
	 * @param array  $params
	 * @param array  $query
	 *
	 * @return Uri
	 */
	public function buildRouteUri(string $name, array $params = [], array $query = []): Uri
	{
		$path = $this->getRouter()
			->buildRoutePath($this, $name, $params);

		return $this->buildUri($path, $query);
	}

	/**
	 * Gets the client IP address.
	 *
	 * Shortcut for {@see ClientInfo::ip()}.
	 *
	 * @param bool $with_port append the client port; only known when the client is the TCP peer
	 *
	 * @return null|string
	 */
	public function getUserIP(bool $with_port = false): ?string
	{
		return $this->client()->ip($with_port);
	}

	/**
	 * Whether the TCP peer is a trusted proxy (see `oz.proxies`).
	 */
	public function isFromTrustedProxy(): bool
	{
		return $this->client()->isFromTrustedProxy();
	}

	/**
	 * Gets the host.
	 *
	 * Shortcut for {@see ClientInfo::host()}.
	 *
	 * @param bool $with_port
	 *
	 * @return string
	 */
	public function getHost(bool $with_port = false): string
	{
		return $this->client()->host($with_port);
	}

	/**
	 * Gets default origin.
	 *
	 * @return string
	 */
	public function getDefaultOrigin(): string
	{
		return $this->client()->defaultOrigin();
	}

	/**
	 * Sends response to the client.
	 *
	 * Should be called only once.
	 * As it will finish/terminate the request.
	 *
	 * @param null|Response $with
	 */
	public function respond(?Response $with = null): never
	{
		if (null !== $with) {
			$this->response = $with;
		}

		(new ResponseHook($this))->dispatch();

		if ($this->auth_state->isStateful()) {
			$this->auth_state->requireStateful()->persist();
		}

		$this->response = ResponseEmitter::prepare($this->response, $this->request);

		$sink = $this->getResponseSink();

		if (null === $sink) {
			ResponseEmitter::emit($this->response);
		} else {
			$sink->send($this->response);
		}

		$this->finish($sink);
	}

	/**
	 * Where the response of this request goes, or null when it is written to PHP's output
	 * (`header()` and `echo`).
	 *
	 * The sink belongs to the request, so a sub-request answers through its root's: it is the
	 * worker loop's, given to {@see OZone::handleRequest()} with the request it came with.
	 */
	public function getResponseSink(): ?ResponseSinkInterface
	{
		return null === $this->parent ? $this->response_sink : $this->parent->getResponseSink();
	}

	/**
	 * Makes sub-request with a given route name.
	 *
	 * @param string $route_name        route name to be called
	 * @param array  $params            route parameters
	 * @param array  $query             query parameters
	 * @param bool   $override_response if true, the response will be set to the current context
	 *
	 * @return Response
	 */
	public function callRoute(
		string $route_name,
		array $params = [],
		array $query = [],
		bool $override_response = true
	): Response {
		return $this->navigator()->callRoute($route_name, $params, $query, $override_response);
	}

	/**
	 * Makes sub-request with a given path.
	 *
	 * @param string $path              path to be called
	 * @param array  $attributes        attributes to be passed to the request
	 * @param array  $query             query parameters to be passed to the request
	 * @param bool   $override_response if true, the response will be set to the current context
	 *
	 * @return Response
	 */
	public function callPath(
		string $path,
		array $attributes = [],
		array $query = [],
		bool $override_response = true
	): Response {
		return $this->navigator()->callPath($path, $attributes, $query, $override_response);
	}

	/**
	 * Redirect the client to a given url.
	 *
	 * @param string|Uri $to     the redirect destination url
	 * @param null|int   $status the redirect HTTP status code
	 */
	public function redirect(string|Uri $to, ?int $status = null): never
	{
		$this->navigator()->redirect($to, $status);
	}

	/**
	 * Redirect to route.
	 *
	 * @param string   $route_name  the route name
	 * @param array    $params      the route parameters
	 * @param array    $query       the query parameters
	 * @param bool     $inform_user whether to inform the user about the redirection
	 * @param null|int $status      the redirect HTTP status code
	 */
	public function redirectRoute(
		string $route_name,
		array $params = [],
		array $query = [],
		bool $inform_user = true,
		?int $status = null,
	): never {
		$this->navigator()->redirectRoute($route_name, $params, $query, $inform_user, $status);
	}

	/**
	 * Gets the request `Origin` header, when it is an http(s) origin.
	 *
	 * Shortcut for {@see ClientInfo::origin()}.
	 */
	public function getRequestOrigin(): ?string
	{
		return $this->client()->origin();
	}

	/**
	 * Gets the request origin or referer.
	 *
	 * Shortcut for {@see ClientInfo::originOrReferer()}; never trust it for security decisions.
	 *
	 * @return null|string
	 */
	public function getRequestOriginOrReferer(): ?string
	{
		return $this->client()->originOrReferer();
	}

	/**
	 * Returns custom headers name for use in CORS.
	 *
	 * Shortcut for {@see ClientInfo::corsAllowedHeaders()}.
	 *
	 * @return array
	 */
	public function getAllowedHeadersNameList(): array
	{
		return $this->client()->corsAllowedHeaders();
	}

	/**
	 * Finish the request.
	 *
	 * @param null|ResponseSinkInterface $sink the sink that took the response, if any
	 */
	private function finish(?ResponseSinkInterface $sink): never
	{
		$runtime = Runtime::current();

		// The response is on its way; what follows runs with the client already served. A sink has
		// sent it already, and flushing PHP's output would write to whatever the server made of
		// STDOUT -- under RoadRunner, the protocol pipe.
		if (null === $sink) {
			$runtime->flushRequest();
		}

		(new FinishHook($this))->dispatch();

		// How a request ends is the runtime's business, and the only thing a persistent process does
		// differently: PHP-FPM exits here, a worker unwinds to its loop. Either way nothing written
		// after a call to this runs. That is the published contract of `respond()` being `: never`,
		// which application code writes against as much as the framework does -- a handler that
		// responds and then falls through to a throw, a redirect, or a `return` it expects to be
		// unreachable. A runtime that merely skipped the exit would run all of it.
		$runtime->terminate();
	}

	/**
	 * Authenticates the request with the matched route's methods, see
	 * {@see ContextAuth::authenticate()}.
	 *
	 * @param RouteInfo $ri
	 *
	 * @throws ForbiddenException
	 */
	private function authenticate(RouteInfo $ri): void
	{
		$this->route_info = $ri;

		$this->auth_state->authenticate($ri, $this->parent?->auth_state);
	}
}
