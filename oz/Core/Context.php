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

namespace OZONE\OZ\Core;

use InvalidArgumentException;
use OZONE\OZ\Exceptions\BaseException;
use OZONE\OZ\Exceptions\ForbiddenException;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\Hooks\Events\RedirectHook;
use OZONE\OZ\Hooks\Events\RequestHook;
use OZONE\OZ\Hooks\Events\ResponseHook;
use OZONE\OZ\Http\Environment;
use OZONE\OZ\Http\Headers;
use OZONE\OZ\Http\Request;
use OZONE\OZ\Http\Response;
use OZONE\OZ\Http\Uri;
use OZONE\OZ\OZone;
use OZONE\OZ\Router\Router;
use OZONE\OZ\Sessions\Session;
use OZONE\OZ\Users\UsersManager;
use PHPUtils\Events\Event;
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

	private bool $running = false;

	private bool $is_sub_request;

	private int $context_type;

	private Router $router;

	private Environment $environment;

	private Request $request;

	private Response $response;

	private Session $session;

	private UsersManager $users_manager;

	/**
	 * RequestHandler constructor.
	 *
	 * @param \OZONE\OZ\Http\Environment  $env
	 * @param null|\OZONE\OZ\Http\Request $request
	 * @param bool                        $is_sub_request
	 * @param bool                        $is_api
	 */
	public function __construct(
		Environment $env,
		?Request $request = null,
		bool $is_sub_request = false,
		bool $is_api = true
	) {
		$this->is_sub_request = $is_sub_request;
		$this->context_type   = $is_api ? self::CONTEXT_TYPE_API : self::CONTEXT_TYPE_WEB;
		$this->router         = $is_api ? OZone::getApiRouter() : OZone::getWebRouter();
		$this->environment    = $env;
		$this->request        = $request ?? Request::createFromEnvironment($env);
		$headers              = new Headers(['Content-Type' => 'text/html; charset=UTF-8']);
		$response             = new Response(200, $headers);
		$this->response       = $response->withProtocolVersion($this->request->getProtocolVersion());

		$this->session       = new Session($this);
		$this->users_manager = new UsersManager($this);
	}

	/**
	 * Context destructor.
	 */
	public function __destruct()
	{
		unset(
			$this->session,
			$this->users_manager,
			$this->response,
			$this->request,
			$this->environment,
			$this->router,
		);
	}

	/**
	 * Disable clone.
	 */
	private function __clone()
	{
	}

	/**
	 * Checks whether json response should be returned.
	 *
	 * @param bool $prefer_json
	 *
	 * @return bool
	 */
	public function shouldReturnJSON(bool $prefer_json = false): bool
	{
		$accept = $this->request->getHeaderLine('HTTP_ACCEPT');

		if ($this->isApiContext()) {
			return \is_int(\strpos($accept, 'application/json'));
		}

		return $prefer_json || \is_int(\strpos($accept, 'application/json'));
	}

	/**
	 * Handle the incoming request.
	 *
	 * @return \OZONE\OZ\Core\Context
	 */
	public function handle(): self
	{
		if ($this->running) {
			throw new RuntimeException('The request is already handled.');
		}

		$this->running = true;

		try {
			$uri           = $this->request->getUri();
			$internal_path = $this->isInternalPath($uri->getPath());

			// prevent request to any path like /oz:redirect, /oz:...
			// this is allowed only in sub-request
			if ($internal_path && !$this->isSubRequest()) {
				throw new ForbiddenException();
			}

			Event::trigger(new RequestHook($this));

			$this->router->handle($this);
		} catch (Throwable $t) {
			BaseException::tryConvert($t)
				->informClient($this);
		}

		return $this;
	}

	/**
	 * Gets env.
	 *
	 * @return \PHPUtils\Store\StoreNotEditable
	 */
	public function getEnv(): StoreNotEditable
	{
		return new StoreNotEditable($this->environment->all());
	}

	/**
	 * Gets request instance object.
	 *
	 * @return \OZONE\OZ\Http\Request
	 */
	public function getRequest(): Request
	{
		return $this->request;
	}

	/**
	 * Gets response.
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function getResponse(): Response
	{
		return $this->response;
	}

	/**
	 * Sets response.
	 *
	 * @param \OZONE\OZ\Http\Response $response
	 *
	 * @return $this
	 */
	public function setResponse(Response $response): self
	{
		$this->response = $response;

		return $this;
	}

	/**
	 * Gets session instance object.
	 *
	 * @return \OZONE\OZ\Sessions\Session
	 */
	public function getSession(): Session
	{
		return $this->session;
	}

	/**
	 * Gets users manager instance object.
	 *
	 * @return \OZONE\OZ\Users\UsersManager
	 */
	public function getUsersManager(): UsersManager
	{
		return $this->users_manager;
	}

	/**
	 * Gets the router instance object.
	 *
	 * @return \OZONE\OZ\Router\Router
	 */
	public function getRouter(): Router
	{
		return $this->router;
	}

	/**
	 * Checks if a given path is an internal path.
	 *
	 * @param string $path
	 *
	 * @return bool
	 */
	public function isInternalPath(string $path): bool
	{
		return \str_starts_with($path, OZone::INTERNAL_PATH_PREFIX);
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
	 * @return \OZONE\OZ\Http\Uri
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
	 * @return \OZONE\OZ\Http\Uri
	 */
	public function buildRouteUri(string $name, array $params = [], array $query = []): Uri
	{
		$path = $this->router->buildRoutePath($this, $name, $params);

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
		$user_ip   = $this->environment->get('REMOTE_ADDR');
		$user_port = $this->environment->get('REMOTE_PORT');

		if (empty($user_ip)) { // we can't trust this request
			return null;
		}

		if ($risky) {
			if (false === $allowed_proxies_only || true === Configs::get('oz.proxies', $user_ip)) {
				$sources = [
					'HTTP_CLIENT_IP',
					'HTTP_X_FORWARDED_FOR',
					'HTTP_X_FORWARDED',
					'HTTP_X_CLUSTER_CLIENT_IP',
					'HTTP_FORWARDED_FOR',
					'HTTP_FORWARDED',
				];

				foreach ($sources as $source) {
					$value = $this->environment->get($source);
					if ($value) {
						$value = \strtolower($value);

						if (\preg_match_all('~(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}|\[[a-f0-9:]+])(?::(\d+))?~', $value, $matches)) {
							$ips   = $matches[1] ?? [];
							$ports = $matches[2] ?? [];
							foreach ($ips as $index => $unsafe_ip) {
								$unsafe_ip   = \str_replace(['[', ']'], '', $unsafe_ip);
								$unsafe_port = $ports[$index] ?? null;

								if (($unsafe_ip = \filter_var(
									$unsafe_ip,
									\FILTER_VALIDATE_IP,
									\FILTER_FLAG_IPV4 |
										\FILTER_FLAG_IPV6 |
										\FILTER_FLAG_NO_PRIV_RANGE |
										\FILTER_FLAG_NO_RES_RANGE
								)) !== false) {
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

			$host = null;

			foreach ($sources as $source) {
				if (!empty($host)) {
					break;
				}

				if (!$this->environment->has($source)) {
					continue;
				}
				$host = $this->environment->get($source);

				if (\array_key_exists($source, $transformers)) {
					$host = $transformers[$source]($host);
				}
			}

			$uri                  = Uri::createFromString($host ?? $this->getMainUrl());
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
	 * Gets main url.
	 *
	 * @return string
	 */
	public function getMainUrl(): string
	{
		return (string) Uri::createFromString(Configs::get('oz.config', 'OZ_API_MAIN_URL'));
	}

	/**
	 * Sends response to the client.
	 *
	 * @param null|\OZONE\OZ\Http\Response $with
	 */
	public function respond(Response $with = null): void
	{
		if (null !== $with) {
			$this->response = $with;
		}

		Event::trigger(new ResponseHook($this));

		$response = $this->response = $this->fixResponse($this->response);

		$chunk_size = 4096;

		// Send response
		if (!\headers_sent()) {
			// Status
			\header(\sprintf(
				'HTTP/%s %s %s',
				$response->getProtocolVersion(),
				$status_code = $response->getStatusCode(),
				$response->getReasonPhrase()
			));

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
	}

	/**
	 * Makes sub-request with a given route name.
	 *
	 * @param string $route_name
	 * @param array  $params
	 * @param array  $query
	 * @param bool   $override_response
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function subRequestRoute(
		string $route_name,
		array $params = [],
		array $query = [],
		bool $override_response = true
	): Response {
		$path = $this->router->buildRoutePath($this, $route_name, $params);

		return $this->subRequestPath($path, $params, $query, $override_response);
	}

	/**
	 * Makes sub-request with a given path.
	 *
	 * @param string $path
	 * @param array  $attributes
	 * @param array  $query
	 * @param bool   $override_response
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function subRequestPath(
		string $path,
		array $attributes = [],
		array $query = [],
		bool $override_response = true
	): Response {
		$env     = $this->environment;
		$request = Request::createFromEnvironment($env);
		$uri     = $request->getUri()
			->withPath($path, true)
			->withQueryArray($query);

		$request = $request->withAttributes($attributes)
			->withUri($uri);

		$context  = new self($env, $request, true, $this->isApiContext());
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
	 * @param string   $url
	 * @param null|int $status
	 */
	public function redirect(string $url, ?int $status = null): void
	{
		$this->checkRecursiveRedirection($url, ['status' => $status]);

		if (!\filter_var($url, \FILTER_VALIDATE_URL)) {
			throw new InvalidArgumentException(\sprintf('Invalid redirect url: %s', $url));
		}

		Event::trigger(new RedirectHook($this, Uri::createFromString($url)));

		if ($this->isApiContext()) {
			$response = $this->response->withRedirect($url, $status);
			$this->setResponse($response);
		} else {
			$this->redirectRoute('oz:redirect', ['url' => $url, 'status' => $status]);
		}
	}

	/**
	 * Redirect to route.
	 *
	 * @param string $route_name
	 * @param array  $params
	 * @param array  $query
	 * @param bool   $inform_user
	 */
	public function redirectRoute(
		string $route_name,
		array $params = [],
		array $query = [],
		bool $inform_user = true
	): void {
		$this->checkRecursiveRedirection(
			$route_name,
			[
				'params'      => $params,
				'query'       => $query,
				'inform_user' => $inform_user,
			]
		);

		$path = $this->router->buildRoutePath($this, $route_name, $params);

		self::$redirect_history[$route_name] = ['path' => $path, 'params' => $params];

		if ($inform_user && !$this->isInternalPath($path)) {
			$uri = Uri::createFromEnvironment($this->environment)
				->withPath($path, true)
				->withQueryArray($query);

			$this->redirect((string) $uri);
		} else {
			$this->subRequestPath($path, $params, $query);
		}
	}

	/**
	 * Gets the request origin or referer.
	 *
	 * don't trust on what you get from this
	 *
	 * @return string
	 */
	public function getRequestOriginOrReferer(): string
	{
		$origin = '';

		if ($this->environment->has('HTTP_ORIGIN')) {
			$origin = $this->environment->get('HTTP_ORIGIN');
		} elseif ($this->environment->has('HTTP_REFERER')) {
			// not safe at all: be aware
			$origin = $this->environment->get('HTTP_REFERER');
		}

		// ignore android-app://com.google.android....
		if (\preg_match('~^https?://~', $origin)) {
			return $origin;
		}

		return '';
	}

	/**
	 * Returns custom headers name for use in CORS.
	 *
	 * @return array
	 */
	public function getAllowedHeadersNameList(): array
	{
		$headers                  = Configs::get('oz.clients', 'OZ_CORS_ALLOWED_HEADERS');
		$headers[]                = \strtolower(Configs::get('oz.config', 'OZ_API_KEY_HEADER_NAME'));
		$headers[]                = \strtolower(Configs::get('oz.sessions', 'OZ_SESSION_TOKEN_HEADER_NAME'));
		$allow_real_method_header = Configs::get('oz.config', 'OZ_API_ALLOW_REAL_METHOD_HEADER');

		if ($allow_real_method_header) {
			$headers[] = \strtolower(Configs::get('oz.config', 'OZ_API_REAL_METHOD_HEADER_NAME'));
		}

		return $headers;
	}

	/**
	 * Try fix response.
	 *
	 * @param \OZONE\OZ\Http\Response $response
	 *
	 * @return \OZONE\OZ\Http\Response
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
