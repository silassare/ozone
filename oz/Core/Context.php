<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Core;

	use OZONE\OZ\Exceptions\BaseException;
	use OZONE\OZ\Exceptions\ForbiddenException;
	use OZONE\OZ\Exceptions\InternalErrorException;
	use OZONE\OZ\Exceptions\MethodNotAllowedException;
	use OZONE\OZ\Exceptions\NotFoundException;
	use OZONE\OZ\Http\Environment;
	use OZONE\OZ\Http\Headers;
	use OZONE\OZ\Http\Request;
	use OZONE\OZ\Http\Response;
	use OZONE\OZ\Http\Uri;
	use OZONE\OZ\OZone;
	use OZONE\OZ\Router\Route;
	use OZONE\OZ\Router\RouteInfo;
	use OZONE\OZ\Router\Router;
	use OZONE\OZ\User\UsersManager;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	class Context
	{
		/**
		 * API context
		 */
		const CONTEXT_TYPE_API = 1;

		/**
		 * WebSite context
		 */
		const CONTEXT_TYPE_WEB = 2;

		/**
		 * @var string
		 */
		private $host = '';

		/**
		 * @var bool
		 */
		private $running = false;

		/**
		 * @var bool
		 */
		private $is_sub_request;

		/**
		 * The request context
		 *
		 * @var int
		 */
		private $context_type;

		/**
		 * @var \OZONE\OZ\Router\Router
		 */
		private $router;

		/**
		 * The current client object
		 *
		 * @var \OZONE\OZ\Db\OZClient
		 */
		private $client;

		/**
		 * The environment
		 *
		 * @var \OZONE\OZ\Http\Environment
		 */
		private $environment;

		/**
		 * The request
		 *
		 * @var \OZONE\OZ\Http\Request
		 */
		private $request;

		/**
		 * The response
		 *
		 * @var \OZONE\OZ\Http\Response
		 */
		private $response;

		/**
		 * @var \OZONE\OZ\Core\Session
		 */
		private $session;

		/**
		 * @var \OZONE\OZ\User\UsersManager
		 */
		private $users_manager;

		/**
		 * Redirection history
		 *
		 * @var array
		 */
		private static $redirect_history = [];

		/**
		 * RequestHandler constructor.
		 *
		 * @param \OZONE\OZ\Http\Environment  $env
		 * @param \OZONE\OZ\Http\Request|null $request
		 * @param bool                        $is_sub_request
		 * @param bool                        $is_api
		 */
		public function __construct(Environment $env, Request $request = null, $is_sub_request = false, $is_api = true)
		{
			$this->is_sub_request = (bool)$is_sub_request;
			$this->context_type   = $is_api ? self::CONTEXT_TYPE_API : self::CONTEXT_TYPE_WEB;
			$this->router         = $is_api ? OZone::getApiRouter() : OZone::getWebRouter();
			$this->environment    = $env;
			$this->users_manager  = new UsersManager($this);
			$this->request        = isset($request) ? $request : Request::createFromEnvironment($env);
			$headers              = new Headers(['Content-Type' => 'text/html; charset=UTF-8']);
			$response             = new Response(200, $headers);
			$this->response       = $response->withProtocolVersion($this->request->getProtocolVersion());
			$this->session        = new Session($this);
		}

		/**
		 * Disable clone.
		 */
		private function __clone() { }

		/**
		 * Gets request instance object.
		 *
		 * @return \OZONE\OZ\Http\Request
		 */
		public function getRequest()
		{
			return $this->request;
		}

		/**
		 * Gets response instance object.
		 *
		 * @return \OZONE\OZ\Http\Response
		 */
		public function getResponse()
		{
			return $this->response;
		}

		/**
		 * Gets session instance object.
		 *
		 * @return \OZONE\OZ\Core\Session
		 */
		public function getSession()
		{
			return $this->session;
		}

		/**
		 * Gets users manager instance object.
		 *
		 * @return \OZONE\OZ\User\UsersManager
		 */
		public function getUsersManager()
		{
			return $this->users_manager;
		}

		/**
		 * Gets the router instance object.
		 *
		 * @return \OZONE\OZ\Router\Router
		 */
		public function getRouter()
		{
			return $this->router;
		}

		/**
		 * Gets ozone client object.
		 *
		 * @return \OZONE\OZ\Db\OZClient
		 */
		public function getClient()
		{
			return $this->client;
		}

		/**
		 * Handle the incoming request.
		 *
		 * @return \OZONE\OZ\Core\Context
		 * @throws \Exception
		 */
		public function handle()
		{
			if ($this->running) {
				throw new \RuntimeException('The request is already handled.');
			}

			$this->running = true;

			try {
				if ($this->request->isOptions()) {
					$this->setInitialHeaders($this->getRequestOriginOrReferer(), 60 * 60);
				} else {
					// set client
					if ($api_key = $this->getApiKey()) {
						$this->client = $this->getApiKeyClient($api_key);
					} else {
						$this->client = $this->getSessionClient();
					}

					$this->session->start();
					$this->processRequest();
				}
			} catch (BaseException $e) {
				// throw again internal error.
				if ($e instanceof InternalErrorException) {
					throw $e;
				}

				$e->informClient($this);
			}

			return $this;
		}

		/**
		 * Process the request.
		 *
		 * @throws \Exception
		 */
		private function processRequest()
		{
			$request = $this->request;
			$uri     = $request->getUri();

			// prevent request to any path like /oz:error, /oz:...
			// this is allowed only in sub-request
			if (!$this->isSubRequest() AND $this->isInternalPath($uri->getPath())) {
				throw new ForbiddenException();
			}

			$this->setInitialHeaders($this->client->getUrl(), $this->client->getSessionLifeTime());

			$results = $this->router->find($request->getMethod(), $uri->getPath());

			switch ($results['status']) {
				case Router::STATUS_NOT_FOUND:
					$this->notFound();
					break;
				case Router::STATUS_METHOD_NOT_ALLOWED:
					throw new MethodNotAllowedException();
					break;
				case Router::STATUS_FOUND:
					/**
					 * @var \OZONE\OZ\Router\Route $route
					 * @var array                  $args
					 */
					list($route, $args) = $results['found'];

					$ctx            = new RouteInfo($this, $route, $args);
					$this->response = $this->runRoute($route, $ctx);
					break;
			}
		}

		/**
		 * Run the route that match the current request path.
		 *
		 * @param \OZONE\OZ\Router\Route     $route
		 * @param \OZONE\OZ\Router\RouteInfo $route_info
		 *
		 * @return \OZONE\OZ\Http\Response
		 * @throws \Exception
		 */
		private function runRoute(Route $route, RouteInfo $route_info)
		{
			static $history = [];

			$history[] = $route->getOptions();

			if (count($history) >= 10) {
				throw new InternalErrorException('Possible recursive redirection.', $history);
			}

			$debug_data = function (Route $route, array $data = []) {
				$info = $this->callableInfo($route->getCallable());

				return [
						   'location' => $info,
						   'route'    => $route->getRoutePath()
					   ] + $data;
			};

			try {
				ob_start();
				$return  = call_user_func($route->getCallable(), $route_info);
				$content = ob_get_clean();
			} catch (\Exception $e) {
				ob_clean();
				throw $e;
			}

			if (!empty($content)) {
				throw new InternalErrorException('Writing to output buffer is not allowed.', $debug_data($route, ['content' => $content]));
			}

			if (!($return instanceof Response)) {
				throw new InternalErrorException(sprintf('Invalid return type, got "%s" will expecting "%s".', gettype($return), Response::class), $debug_data($route));
			}

			return $return;
		}

		/**
		 * Checks if a given path is an internal path.
		 *
		 * @param string $path
		 *
		 * @return bool
		 */
		public function isInternalPath($path)
		{
			return 0 === strpos($path, OZone::INTERNAL_PATH_PREFIX);
		}

		/**
		 * Checks if a given string is like an API key.
		 *
		 * @param string $str
		 *
		 * @return bool
		 */
		public function isApiKeyLike($str)
		{
			if (!is_string($str) OR empty($str)) {
				return false;
			}

			return 1 === preg_match(OZone::API_KEY_REG, $str);
		}

		/**
		 * Checks if we are in WebSite context.
		 *
		 * @return bool
		 */
		public function isWebContext()
		{
			return $this->context_type === self::CONTEXT_TYPE_WEB;
		}

		/**
		 * Checks if we are in API context.
		 *
		 * @return bool
		 */
		public function isApiContext()
		{
			return $this->context_type === self::CONTEXT_TYPE_API;
		}

		/**
		 * Checks it is a sub-request.
		 *
		 * @return bool
		 */
		public function isSubRequest()
		{
			return $this->is_sub_request;
		}

		/**
		 * Gets base url.
		 *
		 * @return string
		 */
		public function getBaseUrl()
		{
			return $this->buildUri('/')
						->getBaseUrl();
		}

		/**
		 * Build URI with a given path and query data.
		 *
		 * @param string $path
		 * @param array  $query
		 *
		 * @return \OZONE\OZ\Http\Uri
		 */
		public function buildUri($path, array $query = [])
		{
			// we don't use (trust) the request uri host
			// so we use our host
			return $this->request->getUri()
								 ->withHost($this->getHost())
								 ->withPath($path)
								 ->withQueryArray($query);
		}

		/**
		 * Build URI with a given path and query data.
		 *
		 * @param string $route_name
		 * @param array  $args
		 * @param array  $query
		 *
		 * @return \OZONE\OZ\Http\Uri
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public function buildRouteUri($route_name, array $args = [], array $query = [])
		{
			$path = $this->router->buildRoutePath($route_name, $args);

			return $this->buildUri($path, $query);
		}

		/**
		 * Gets the host.
		 *
		 * @return string
		 */
		public function getHost()
		{
			if (empty($this->host)) {
				// this comes from https://stackoverflow.com/a/8909559

				$possibleHostSources = [
					'HTTP_X_FORWARDED_HOST',
					'HTTP_HOST',
					'SERVER_NAME',
					'SERVER_ADDR'
				];

				$sourceTransformations['HTTP_X_FORWARDED_HOST'] = function ($value) {
					$parts = explode(',', $value);

					return trim(end($parts));
				};

				$host = null;

				foreach ($possibleHostSources as $source) {
					if (!empty($host)) break;
					if (!$this->environment->has($source)) continue;
					$host = $this->environment->get($source);
					if (array_key_exists($source, $sourceTransformations)) {
						$host = $sourceTransformations[$source]($host);
					}
				}

				if (!$host) {
					$host = Uri::createFromString($this->getMainUrl())
							   ->getHost();
				} else {
					// Remove port number from host
					$host = preg_replace('~:\d+$~', '', $host);
				}

				$this->host = trim($host);
			}

			return $this->host;
		}

		/**
		 * Gets main url.
		 *
		 * @return string
		 */
		public function getMainUrl()
		{
			return Uri::createFromString(SettingsManager::get('oz.config', 'OZ_API_MAIN_URL'));
		}

		/**
		 * Gets the request origin or referer
		 *
		 * don't trust on what you get from this
		 *
		 * @return string
		 */
		private function getRequestOriginOrReferer()
		{
			$origin = '';

			if ($this->environment->has('HTTP_ORIGIN')) {
				$origin = $this->environment->get('HTTP_ORIGIN');
			} elseif ($this->environment->has('HTTP_REFERER')) {
				// not safe at all: be aware
				$origin = $this->environment->get('HTTP_REFERER');
			}

			// ignore android-app://com.google.andr....
			if (preg_match('~^https?://~', $origin)) {
				return $origin;
			}

			return '';
		}

		/**
		 * Returns custom headers name for use in CORS
		 *
		 * @return array
		 */
		private function getCustomHeadersNameList()
		{
			$custom_headers[]         = 'accept';
			$custom_headers[]         = strtolower(SettingsManager::get('oz.config', 'OZ_API_KEY_HEADER_NAME'));
			$allow_real_method_header = SettingsManager::get('oz.config', 'OZ_API_ALLOW_REAL_METHOD_HEADER');

			if ($allow_real_method_header) {
				$custom_headers[] = strtolower(SettingsManager::get('oz.config', 'OZ_API_REAL_METHOD_HEADER_NAME'));
			}

			return $custom_headers;
		}

		/**
		 * Sets required http headers
		 *
		 * @param string $client_url
		 * @param int    $life_time
		 *
		 * @return $this
		 * @throws \OZONE\OZ\Exceptions\ForbiddenException
		 */
		private function setInitialHeaders($client_url, $life_time)
		{
			$rule   = SettingsManager::get('oz.clients', 'OZ_CORS_ALLOW_RULE');
			$h_list = [];

			// header spoofing can help hacker bypass this
			// so don't be 100% sure, lol
			$origin         = $this->getRequestOriginOrReferer();
			$allowed_origin = $this->getMainUrl();

			switch ($rule) {
				case 'deny':
					if (empty($origin)) {
						$origin = $allowed_origin;
					}
					break;
				case 'any':
					if (empty($origin)) {
						$origin = $allowed_origin;
					} else {
						$allowed_origin = $origin;
					}
					break;
				case 'check':
				default:
					if (empty($origin)) {
						$origin = $allowed_origin;
					} else {
						$allowed_origin = $client_url;
					}
			}

			$a = Uri::createFromString($allowed_origin);
			$b = Uri::createFromString($origin);
			// let's avoid the click-jacking
			$h_list['X-Frame-Options'] = 'DENY';

			if ($this->request->isOptions()) {
				// enable self made headers
				$h_list['Access-Control-Allow-Headers'] = $this->getCustomHeadersNameList();
				$h_list['Access-Control-Allow-Methods'] = ['OPTIONS', 'GET', 'POST', 'PATCH', 'PUT', 'DELETE'];
				$h_list['Access-Control-Max-Age']       = $life_time;
			} elseif (!$this->isSubRequest() AND $a->getHost() !== $b->getHost()) {
				// we don't throw this exception in sub-request
				// scenario:
				//  - We want to show error page because the cross site request origin was not allowed
				//  - The sub-request is made with the original request environment
				throw new ForbiddenException('OZ_CROSS_SITE_REQUEST_NOT_ALLOWED', ['origin' => (string)$origin]);
			}

			// Remember: CORS is not security. Do not rely on CORS to secure your web site/application.
			// If you are serving protected data, use cookies or OAuth tokens or something
			// other than the Origin header to secure that data. The Access-Control-Allow-Origin header
			// in CORS only dictates which origins should be allowed to make cross-origin requests.
			// Don't rely on it for anything more.
			// allow browser to make CORS request
			$h_list['Access-Control-Allow-Origin'] = $origin;
			// allow browser to send CORS request with cookies
			$h_list['Access-Control-Allow-Credentials'] = 'true';

			foreach ($h_list as $key => $value) {
				$this->response = $this->response->withHeader($key, $value);
			}

			return $this;
		}

		/**
		 * Gets client with api key
		 *
		 * @param string $api_key
		 *
		 * @return \OZONE\OZ\Db\OZClient|null
		 * @throws \OZONE\OZ\Exceptions\ForbiddenException
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		private function getApiKeyClient($api_key)
		{
			$client = ClientManager::getClientWithApiKey($api_key);

			if (!$client OR !$client->getValid()) {
				throw new ForbiddenException('OZ_YOUR_API_KEY_IS_NOT_VALID', [
					'url'     => (string)$this->request->getUri(),
					'api_key' => $api_key
				]);
			}

			return $client;
		}

		/**
		 * Gets client from session
		 *
		 * @return \OZONE\OZ\Db\OZClient
		 * @throws \OZONE\OZ\Exceptions\BaseException
		 */
		private function getSessionClient()
		{
			// please BE AWARE!!!

			$sid_name = SettingsManager::get('oz.config', 'OZ_API_SESSION_ID_NAME');

			$sid = $this->request->getCookieParam($sid_name);

			if (empty($sid)) {
				throw new ForbiddenException('OZ_MISSING_API_KEY');
			}

			$client = ClientManager::getClientWithSessionId($sid);

			if (is_null($client) OR !$client->getValid()) {
				throw new ForbiddenException('OZ_SESSION_INVALID');
			}

			return $client;
		}

		/**
		 * Gets the api key
		 *
		 * @return string|null
		 */
		private function getApiKey()
		{
			$api_key      = null;
			$api_key_name = SettingsManager::get('oz.config', 'OZ_API_KEY_HEADER_NAME');
			$env_key      = sprintf('HTTP_%s', strtoupper(str_replace('-', '_', $api_key_name)));

			if ($this->environment->has($env_key)) {
				$api_key = $this->environment->get($env_key);
			} elseif (defined('OZ_OZONE_DEFAULT_API_KEY')) {
				$api_key = OZ_OZONE_DEFAULT_API_KEY;
			}

			return $this->isApiKeyLike($api_key) ? $api_key : null;
		}

		/**
		 * Try fix response.
		 *
		 * @param \OZONE\OZ\Http\Response $response
		 *
		 * @return \OZONE\OZ\Http\Response
		 */
		private function fixResponse(Response $response)
		{
			if ($response->isEmpty()) {
				return $response->withoutHeader('Content-Type')
								->withoutHeader('Content-Length');
			}

			$size = $response->getBody()
							 ->getSize();
			if ($size !== null) {
				$response = $response->withHeader('Content-Length', (string)$size);
			}

			return $response;
		}

		/**
		 * Sends response to the client.
		 *
		 * @param \OZONE\OZ\Http\Response|null $response
		 *
		 * @throws \Exception
		 */
		public function respond(Response $response = null)
		{
			if (!$response) {
				$response = $this->response;
			}

			// TODO create real hook logic
			$response = $this->session->responseReadyHook($response);
			$response = $this->fixResponse($response);

			$chunkSize = 4096;

			// Send response
			if (!headers_sent()) {
				// Status
				header(sprintf(
					'HTTP/%s %s %s',
					$response->getProtocolVersion(),
					$response->getStatusCode(),
					$response->getReasonPhrase()
				));

				// Headers
				foreach ($response->getHeaders() as $name => $values) {
					foreach ($values as $value) {
						header(sprintf('%s: %s', $name, $value), false);
					}
				}
			}

			// Body
			if (!$response->isEmpty()) {
				$body = $response->getBody();
				if ($body->isSeekable()) {
					$body->rewind();
				}

				$contentLength = $response->getHeaderLine('Content-Length');
				if (!$contentLength) {
					$contentLength = $body->getSize();
				}

				if (isset($contentLength)) {
					$amountToRead = $contentLength;
					while ($amountToRead > 0 && !$body->eof()) {
						$data = $body->read(min($chunkSize, $amountToRead));
						echo $data;

						$amountToRead -= strlen($data);

						if (connection_status() != CONNECTION_NORMAL) {
							break;
						}
					}
				} else {
					while (!$body->eof()) {
						echo $body->read($chunkSize);
						if (connection_status() != CONNECTION_NORMAL) {
							break;
						}
					}
				}
			}

			// we finish
			exit;
		}

		/**
		 * Makes sub-request with a given route name.
		 *
		 * @param string $route_name
		 * @param array  $args
		 * @param array  $query
		 * @param bool   $respond
		 *
		 * @return \OZONE\OZ\Http\Response
		 * @throws \Exception
		 */
		public function subRequestRoute($route_name, array $args = [], array $query = [], $respond = true)
		{
			$path = $this->router->buildRoutePath($route_name, $args);

			return $this->subRequestPath($path, $args, $query, $respond);
		}

		/**
		 * Makes sub-request with a given path.
		 *
		 * @param string $path
		 * @param array  $attributes
		 * @param array  $query
		 * @param bool   $respond
		 *
		 * @return \OZONE\OZ\Http\Response
		 * @throws \Exception
		 */
		public function subRequestPath($path, array $attributes = [], array $query = [], $respond = true)
		{
			$env     = $this->environment;
			$request = Request::createFromEnvironment($env);
			$uri     = $request->getUri()
							   ->withPath($path)
							   ->withQueryArray($query);

			$request = $request->withAttributes($attributes)
							   ->withUri($uri);

			$context  = new static($env, $request, true, $this->isApiContext());
			$response = $context->handle()
								->getResponse();

			if ($respond) {
				$this->respond($response);
			}

			return $response;
		}

		/**
		 * Redirect the client to a given url.
		 *
		 * @param string   $url
		 * @param int|null $status
		 *
		 * @throws \Exception
		 */
		public function redirect($url, $status = null)
		{
			$url = (string)$url;

			$this->checkRecursiveRedirection($url,
				[
					'status' => $status
				]);

			if (!filter_var($url, FILTER_VALIDATE_URL)) {
				throw new \InvalidArgumentException(sprintf('Invalid redirect url: %s', $url));
			}

			if ($this->isApiContext()) {
				$response = $this->response->withRedirect($url, $status);
				$this->respond($response);
			} else {
				$this->redirectRoute('oz:redirect', ['url' => $url, 'status' => $status]);
			}
		}

		/**
		 * Redirect to route.
		 *
		 * @param string $route_name
		 * @param array  $args
		 * @param array  $query
		 * @param bool   $inform_user
		 *
		 * @throws \Exception
		 */
		public function redirectRoute($route_name, array $args = [], array $query = [], $inform_user = true)
		{
			$this->checkRecursiveRedirection($route_name,
				[
					'args'        => $args,
					'$query'      => $query,
					'inform_user' => $inform_user
				]);

			$path = $this->router->buildRoutePath($route_name, $args);

			self::$redirect_history[$route_name] = ['path' => $path, 'args' => $args];

			if (!$this->isInternalPath($path) AND $inform_user) {
				$uri = Uri::createFromEnvironment($this->environment)
						  ->withPath($path)
						  ->withQueryArray($query);

				$this->redirect((string)$uri);
			} else {
				$this->subRequestPath($path, $args, $query);
			}
		}

		/**
		 * Checks for recursive redirection.
		 *
		 * @param string $path
		 * @param array  $info
		 *
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		private function checkRecursiveRedirection($path, array $info)
		{
			if (isset(self::$redirect_history[$path])) {
				$debug = [
					'target'  => $path,
					'data'    => $info,
					'history' => self::$redirect_history
				];

				throw new InternalErrorException('OZ_RECURSIVE_REDIRECTION', $debug);
			}

			self::$redirect_history[$path] = $info;
		}

		/**
		 * Called when no route founds for the requested resource
		 *
		 * @throws \OZONE\OZ\Exceptions\ForbiddenException
		 * @throws \OZONE\OZ\Exceptions\NotFoundException
		 */
		private function notFound()
		{
			$uri = $this->request->getUri();
			// is it root
			if ($this->isApiContext() AND $uri->getPath() === '/') {
				// TODO api doc
				// show api usage doc when this condition are met:
				//  - we are in api mode
				//	- debugging or allowed in settings
				// show welcome friendly page when this conditions are met:
				//  - we are in web mode
				throw new ForbiddenException();
			} else {
				throw new NotFoundException();
			}
		}

		/**
		 * Try get callable file code source start and end line numbers.
		 *
		 * @param callable $c
		 *
		 * @return array|bool
		 */
		private function callableInfo(callable $c)
		{
			$r = null;
			try {
				if ($c instanceof \Closure) {
					$r = new \ReflectionFunction($c);
				} elseif (is_callable($c)) {
					$r = new \ReflectionMethod($c);
				}
				if ($r) {
					return [
						'file'  => $r->getFileName(),
						'start' => $r->getStartLine() - 1,
						'end'   => $r->getEndLine()
					];
				}
			} catch (\ReflectionException $e) {
			}

			return false;
		}
	}