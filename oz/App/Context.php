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
use LogicException;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodInterface;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodStatefulInterface;
use OZONE\Core\Auth\StatefulAuthenticationMethodStore;
use OZONE\Core\Exceptions\BaseException;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Hooks\Events\FinishHook;
use OZONE\Core\Hooks\Events\RedirectHook;
use OZONE\Core\Hooks\Events\RequestHook;
use OZONE\Core\Hooks\Events\ResponseHook;
use OZONE\Core\Http\Headers;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Http\Request;
use OZONE\Core\Http\Response;
use OZONE\Core\Http\Uri;
use OZONE\Core\OZone;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Utils\Utils;
use PHPUtils\Store\StoreNotEditable;
use Throwable;

/**
 * Class Context.
 */
final class Context
{
	public const CONTEXT_TYPE_API = 1;

	public const CONTEXT_TYPE_WEB = 2;

	private static array $redirect_history = [];

	private string $host           = '';
	private string $host_with_port = '';

	private bool $handle_called = false;

	private bool $is_sub_request;

	private int $context_type;

	private ?Router $t_router = null;

	private HTTPEnvironment $http_environment;

	private Request $request;

	private Response $response;

	private AuthUsers $users;
	private ?AuthenticationMethodInterface $auth;

	private ?RouteInfo $route_info = null;

	private static ?self $_root    = null;
	private static ?self $_current = null;

	/**
	 * Context constructor.
	 *
	 * @param HTTPEnvironment $http_env
	 * @param null|Request    $request
	 * @param null|Context    $parent
	 * @param bool            $is_api
	 */
	public function __construct(
		HTTPEnvironment $http_env,
		?Request $request = null,
		private readonly ?self $parent = null,
		bool $is_api = true
	) {
		if (null === self::$_root) {
			self::$_root = $this;
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

		$this->users = new AuthUsers($this);
	}

	/**
	 * Context destructor.
	 */
	public function __destruct()
	{
		unset(
			$this->users,
			$this->response,
			$this->request,
			$this->http_environment,
			$this->t_router,
			$this->route_info,
			$this->auth,
		);
	}

	/**
	 * Disable clone.
	 */
	private function __clone() {}

	/**
	 * Returns the current context.
	 */
	public static function current(): self
	{
		if (!isset(self::$_current)) {
			if (!isset(self::$_root)) {
				throw new RuntimeException('No context was created.');
			}

			return self::$_root;
		}

		return self::$_current;
	}

	/**
	 * Gets the root context.
	 *
	 * @return self
	 */
	public static function root(): self
	{
		if (!isset(self::$_root)) {
			throw new RuntimeException('No context was created.');
		}

		return self::$_root;
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
	 * @return Context
	 */
	public function handle(): self
	{
		if ($this->handle_called) {
			throw new RuntimeException('The request is already handled.');
		}

		$this->handle_called = true;

		$previous             = self::$_current;
		self::$_current       = $this;

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
				->handle($this, function (RouteInfo $route_info) {
					$this->authenticate($route_info);
				});
		} catch (Throwable $t) {
			BaseException::tryConvert($t)
				->informClient($this);
		} finally {
			self::$_current = $previous;
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
	 *
	 * @return $this
	 */
	public function setResponse(Response $response): self
	{
		$this->response = $response;

		return $this;
	}

	/**
	 * Gets current auth.
	 *
	 * @return AuthenticationMethodInterface
	 */
	public function auth(): AuthenticationMethodInterface
	{
		// if not defined we throw exception
		// as this seems to be required but not defined
		// in the route options or called before the route was found
		if (!isset($this->auth)) {
			if (!isset($this->route_info)) {
				throw new RuntimeException(
					\sprintf('"%s" was called before a route was found.', __METHOD__)
				);
			}

			throw (new RuntimeException(
				\sprintf('No auth method was defined for the current route but "%s" was called.', __METHOD__)
			)
			)->suspectCallable(
				$this->route_info->route()
					->getHandler()
			);
		}

		return $this->auth;
	}

	/**
	 * Checks if we have an authenticated user.
	 *
	 * @return bool true when user is authenticated, false otherwise
	 */
	public function hasAuthenticatedUser(): bool
	{
		try {
			return (bool) $this->auth()->user();
		} catch (Throwable) {
			return false;
		}
	}

	/**
	 * Checks if we have a stateful auth method.
	 *
	 * @return bool
	 */
	public function hasStatefulAuth(): bool
	{
		try {
			return (bool) $this->requireStatefulAuth();
		} catch (Throwable) {
			return false;
		}
	}

	/**
	 * Gets {@link AuthenticationMethodStatefulInterface} instance object.
	 *
	 * @return AuthenticationMethodStatefulInterface
	 */
	public function requireStatefulAuth(): AuthenticationMethodStatefulInterface
	{
		$auth = $this->auth();
		if ($auth instanceof AuthenticationMethodStatefulInterface) {
			return $auth;
		}

		throw new RuntimeException(
			\sprintf('"%s" was called but the current auth method is not stateful.', __METHOD__),
			[
				'auth' => \get_class($auth),
			]
		);
	}

	/**
	 * Try to get the state if the auth method is stateful.
	 *
	 * @return null|StatefulAuthenticationMethodStore
	 */
	public function authStore(): ?StatefulAuthenticationMethodStore
	{
		try {
			return $this->requireAuthStore();
		} catch (Throwable) {
		}

		return null;
	}

	/**
	 * Make sure we have a stateful auth method and return its store.
	 *
	 * @return StatefulAuthenticationMethodStore
	 */
	public function requireAuthStore(): StatefulAuthenticationMethodStore
	{
		return $this->requireStatefulAuth()
			->store();
	}

	/**
	 * Gets attached {@link AuthUsers} instance object.
	 *
	 * @return AuthUsers
	 */
	public function getAuthUsers(): AuthUsers
	{
		return $this->users;
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
			->withHost($this->getHost())
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
	 * Gets user IP address.
	 *
	 * @param bool $with_port            Should we append port to the IP address ? Default: false. (Mostly for user
	 *                                   under IPS...)
	 * @param bool $risky                Should we use risky method ? Default: false. (For user under ISP/Proxy...)
	 * @param bool $allowed_proxies_only In risky mode should we accept allowed proxies only ? Default: true
	 *
	 * @return null|string
	 */
	public function getUserIP(bool $with_port = false, bool $risky = false, bool $allowed_proxies_only = true): ?string
	{
		// You're crazy to rely on something other than this :)
		$user_ip   = $this->http_environment->get('REMOTE_ADDR');
		$user_port = $this->http_environment->get('REMOTE_PORT');

		if (empty($user_ip)) { // we can't trust this request
			return null;
		}

		if ($risky) {
			if (false === $allowed_proxies_only || true === Settings::get('oz.proxies', $user_ip)) {
				$sources = [
					'HTTP_CLIENT_IP',
					'HTTP_X_FORWARDED_FOR',
					'HTTP_X_FORWARDED',
					'HTTP_X_CLUSTER_CLIENT_IP',
					'HTTP_FORWARDED_FOR',
					'HTTP_FORWARDED',
				];

				foreach ($sources as $source) {
					$value = $this->http_environment->get($source);
					if ($value) {
						$value = \strtolower($value);

						if (
							\preg_match_all(
								'~(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}|\[[a-f0-9:]+])(?::(\d+))?~',
								$value,
								$matches
							)
						) {
							$ips   = $matches[1] ?? [];
							$ports = $matches[2] ?? [];
							foreach ($ips as $index => $unsafe_ip) {
								$unsafe_ip   = \str_replace(['[', ']'], '', $unsafe_ip);
								$unsafe_port = $ports[$index] ?? null;

								if (
									($unsafe_ip = \filter_var(
										$unsafe_ip,
										\FILTER_VALIDATE_IP,
										\FILTER_FLAG_IPV4
										| \FILTER_FLAG_IPV6
										| \FILTER_FLAG_NO_PRIV_RANGE
										| \FILTER_FLAG_NO_RES_RANGE
									)) !== false
								) {
									$user_ip   = $unsafe_ip;
									$user_port = $unsafe_port;

									break 2;
								}
							}
						}
					}
				}
			}
		}

		if ($with_port && !empty($user_port)) {
			if (\str_contains($user_ip, ':')) {
				// Woo hah!!! we got an IPV6 address,
				// In order to append the port,
				// We should enclose it in square brackets []
				$user_ip = '[' . $user_ip . ']';
			}

			$user_ip .= ':' . $user_port;
		}

		return $user_ip;
	}

	/**
	 * Gets the host.
	 *
	 * @param bool $with_port
	 *
	 * @return string
	 */
	public function getHost(bool $with_port = false): string
	{
		if (empty($this->host)) {
			$sources = [
				'HTTP_X_FORWARDED_HOST',
				'HTTP_HOST',
				'SERVER_NAME',
				'SERVER_ADDR',
			];

			$transformers['HTTP_X_FORWARDED_HOST'] = static function ($value) {
				$parts = \explode(',', $value);

				return \trim(\end($parts));
			};

			$found = null;

			foreach ($sources as $source) {
				if (!$this->http_environment->has($source)) {
					continue;
				}

				$found = $this->http_environment->get($source);

				if (\array_key_exists($source, $transformers)) {
					$found = $transformers[$source]($found);
				}

				if (!empty($found)) {
					break;
				}
			}

			$found = $found ?? $this->getDefaultOrigin();

			if (!\preg_match('~^https?://~', $found)) {
				$found = 'https://' . $found;
			}

			$uri                  = Uri::createFromString($found);
			$this->host           = $uri->getHost();
			$port                 = $uri->getPort();
			$this->host_with_port = $this->host;

			if ($port) {
				$this->host_with_port .= ':' . $port;
			}
		}

		return $with_port ? $this->host_with_port : $this->host;
	}

	/**
	 * Gets default origin.
	 *
	 * @return string
	 */
	public function getDefaultOrigin(): string
	{
		return (string) Uri::createFromString(Settings::get('oz.request', 'OZ_DEFAULT_ORIGIN'));
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

		if ($this->hasStatefulAuth()) {
			$this->requireStatefulAuth()->persist();
		}

		$response = $this->response = $this->fixResponse($this->response);

		$chunk_size = 4096;

		// Send response
		if (!\headers_sent()) {
			// Status
			\header(
				\sprintf(
					'HTTP/%s %s %s',
					$response->getProtocolVersion(),
					$status_code = $response->getStatusCode(),
					$response->getReasonPhrase()
				)
			);

			// Headers
			foreach ($response->getHeaders() as $name => $values) {
				$replace = (0 === \strcasecmp($name, 'Content-Type'));
				foreach ($values as $value) {
					\header(\sprintf('%s: %s', $name, $value), $replace, $status_code);
				}
			}
		}

		// Body
		if (!$response->isEmpty()) {
			$body = $response->getBody();

			if ($body->isSeekable()) {
				$body->rewind();
			}

			$content_length = (int) $response->getHeaderLine('Content-Length');

			if (!$content_length) {
				$content_length = $body->getSize();
			}

			if (isset($content_length)) {
				$amount_to_read = $content_length;

				while ($amount_to_read > 0 && !$body->eof()) {
					$data = $body->read(\min($chunk_size, $amount_to_read));
					echo $data;

					$amount_to_read -= \strlen($data);

					if (\CONNECTION_NORMAL !== \connection_status()) {
						$body->close();

						break;
					}
				}
			} else {
				while (!$body->eof()) {
					echo $body->read($chunk_size);

					if (\CONNECTION_NORMAL !== \connection_status()) {
						$body->close();

						break;
					}
				}
			}
		}

		$this->finish();
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
		$path = $this->getRouter()
			->buildRoutePath($this, $route_name, $params);

		return $this->callPath($path, $params, $query, $override_response);
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
		$http_env = $this->http_environment;
		$request  = Request::createFromHTTPEnvironment($http_env);
		$uri      = $request->getUri()
			->withPath($path, true)
			->withQueryArray($query);

		$request = $request->withAttributes($attributes)
			->withUri($uri);

		$context  = new self($http_env, $request, $this, $this->isApiContext());
		$response = $context->handle()
			->getResponse();

		if ($override_response) {
			$this->setResponse($response);
		}

		return $response;
	}

	/**
	 * Redirect the client to a given url.
	 *
	 * @param string   $url    the redirect destination url
	 * @param null|int $status the redirect HTTP status code
	 */
	public function redirect(string $url, ?int $status = null): never
	{
		$this->checkRecursiveRedirection($url, ['status' => $status]);

		if (!\filter_var($url, \FILTER_VALIDATE_URL)) {
			throw new InvalidArgumentException(\sprintf('Invalid redirect url: %s', $url));
		}

		(new RedirectHook($this, Uri::createFromString($url)))->dispatch();

		if ($this->isApiContext()) {
			$response = $this->response->withRedirect($url, $status);
			$this->setResponse($response);
			$this->respond();
		} else {
			$this->redirectRoute('oz:redirect', ['url' => $url, 'status' => $status]);
		}
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
		$this->checkRecursiveRedirection(
			$route_name,
			[
				'params'      => $params,
				'query'       => $query,
				'inform_user' => $inform_user,
			]
		);

		$path = $this->getRouter()
			->buildRoutePath($this, $route_name, $params);

		self::$redirect_history[$route_name] = ['path' => $path, 'params' => $params];

		if ($inform_user && !OZone::isInternalPath($path)) {
			$uri = Uri::createFromEnvironment($this->http_environment)
				->withPath($path, true)
				->withQueryArray($query);

			$this->redirect((string) $uri, $status);
		} else {
			$this->callPath($path, $params, $query);
			$this->respond();
		}
	}

	/**
	 * Gets the request origin or referer.
	 *
	 * don't trust on what you get from this
	 *
	 * @return null|string
	 */
	public function getRequestOriginOrReferer(): ?string
	{
		$origin = '';

		if ($this->http_environment->has('HTTP_ORIGIN')) {
			$origin = $this->http_environment->get('HTTP_ORIGIN');
		} elseif ($this->http_environment->has('HTTP_REFERER')) {
			// not safe at all: be aware
			$origin = $this->http_environment->get('HTTP_REFERER');
		}

		// ignore android-app://com.google.android....
		if (\preg_match('~^https?://~', $origin)) {
			return $origin;
		}

		return null;
	}

	/**
	 * Returns custom headers name for use in CORS.
	 *
	 * @return array
	 */
	public function getAllowedHeadersNameList(): array
	{
		$access_control_headers = $this->request->getHeaderLine('HTTP_ACCESS_CONTROL_REQUEST_HEADERS');
		$provided               = [];
		if (!empty($access_control_headers)) {
			$provided = \explode(',', $access_control_headers);
		}

		$declared                 = Settings::get('oz.request', 'OZ_CORS_ALLOWED_HEADERS');
		$declared[]               = \strtolower(Settings::get('oz.auth', 'OZ_AUTH_API_KEY_HEADER_NAME'));
		$allow_real_method_header = Settings::get('oz.request', 'OZ_ALLOW_REAL_METHOD_HEADER');

		if ($allow_real_method_header) {
			$declared[] = \strtolower(Settings::get('oz.request', 'OZ_REAL_METHOD_HEADER_NAME'));
		}

		$bundle = \array_merge($declared, $provided);

		return \array_unique(
			\array_map(static function ($entry) {
				return \strtolower(\trim($entry));
			}, $bundle)
		);
	}

	/**
	 * Finish the request.
	 */
	private function finish(): never
	{
		// Finish the request
		if (\function_exists('fastcgi_finish_request')) {
			fastcgi_finish_request();
		} elseif (!\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
			Utils::closeOutputBuffers(0, true);
		}

		(new FinishHook($this))->dispatch();

		exit;
	}

	/**
	 * Authenticates the request.
	 *
	 * When no auth method was defined for the current route, just returns null.
	 * When auth method was defined for the current route:
	 *  - If the request didn't satisfy none of them, an exception is thrown.
	 *  - The first auth method that satisfies the request is used to authenticate the request.
	 *
	 * @param RouteInfo $ri
	 *
	 * @throws ForbiddenException
	 */
	private function authenticate(RouteInfo $ri): void
	{
		if (isset($this->auth)) {
			throw new LogicException('Authentication already done.');
		}

		$this->route_info = $ri;
		$route            = $ri->route();
		$auths_methods    = $route->getOptions()
			->getAuthenticationMethods();

		if (empty($auths_methods)) {
			return;
		}

		// For sub request we reuse the parent request auth
		// if it's one of the auth methods defined for the current route
		if ($this->is_sub_request && isset($this->parent->auth)) {
			$parent_auth = $this->parent->auth;
			if (\in_array($parent_auth::class, $auths_methods, true)) {
				$this->auth = $parent_auth;

				return;
			}
		}

		/** @var AuthenticationMethodInterface $class */
		foreach ($auths_methods as $class) {
			$instance = $class::get($ri, 'Authentication required.');

			if ($instance->satisfied()) {
				$instance->authenticate();

				$this->auth = $instance;

				return;
			}
		}

		throw new ForbiddenException('Authentication required.');
	}

	/**
	 * Try fix response.
	 *
	 * @param Response $response
	 *
	 * @return Response
	 */
	private function fixResponse(Response $response): Response
	{
		if ($response->isEmpty()) {
			return $response->withoutHeader('Content-Type')
				->withoutHeader('Content-Length');
		}

		$size = $response->getBody()
			->getSize();

		if (null !== $size) {
			return $response->withHeader('Content-Length', (string) $size);
		}

		return $response;
	}

	/**
	 * Checks for recursive redirection.
	 *
	 * @param string $path
	 * @param array  $info
	 */
	private function checkRecursiveRedirection(string $path, array $info): void
	{
		if (isset(self::$redirect_history[$path])) {
			$debug = [
				'target'  => $path,
				'data'    => $info,
				'history' => self::$redirect_history,
			];

			throw new RuntimeException('OZ_RECURSIVE_REDIRECTION', $debug);
		}

		self::$redirect_history[$path] = $info;
	}
}
