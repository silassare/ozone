<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Http;

use Closure;
use InvalidArgumentException;
use OZONE\OZ\Core\SettingsManager;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * Request
 *
 * This class represents an HTTP request. It manages
 * the request method, URI, headers, cookies, and body
 * according to the PSR-7 standard.
 *
 * @link https://github.com/php-fig/http-message/blob/master/src/MessageInterface.php
 * @link https://github.com/php-fig/http-message/blob/master/src/RequestInterface.php
 * @link https://github.com/php-fig/http-message/blob/master/src/ServerRequestInterface.php
 */
class Request extends Message implements ServerRequestInterface
{
	/**
	 * The request method
	 *
	 * @var string
	 */
	protected $method;

	/**
	 * The original request method (ignoring override)
	 *
	 * @var string
	 */
	protected $originalMethod;

	/**
	 * The request URI object
	 *
	 * @var \OZONE\OZ\Http\Uri
	 */
	protected $uri;

	/**
	 * The request URI target (path + query string)
	 *
	 * @var string
	 */
	protected $requestTarget;

	/**
	 * The request query string params
	 *
	 * @var array
	 */
	protected $queryParams;

	/**
	 * The request cookies
	 *
	 * @var array
	 */
	protected $cookies;

	/**
	 * The server environment variables at the time the request was created.
	 *
	 * @var array
	 */
	protected $serverParams;

	/**
	 * The request attributes (route segment names and values)
	 *
	 * @var \OZONE\OZ\Http\Collection
	 */
	protected $attributes;

	/**
	 * The request body parsed (if possible) into a PHP array or object
	 *
	 * @var null|array|object
	 */
	protected $bodyParsed = false;

	/**
	 * List of request body parsers (e.g., url-encoded, JSON, XML, multipart)
	 *
	 * @var callable[]
	 */
	protected $bodyParsers = [];

	/**
	 * List of uploaded files
	 *
	 * @var \OZONE\OZ\Http\UploadedFile[]
	 */
	protected $uploadedFiles;

	/**
	 * Valid request methods
	 *
	 * @var string[]
	 */
	protected $validMethods = [
		'CONNECT' => 1,
		'DELETE'  => 1,
		'GET'     => 1,
		'HEAD'    => 1,
		'OPTIONS' => 1,
		'PATCH'   => 1,
		'POST'    => 1,
		'PUT'     => 1,
		'TRACE'   => 1,
	];

	/**
	 * Creates new HTTP request.
	 *
	 * Adds a host header when none was provided and a host is defined in uri.
	 *
	 * @param string          $method        The request method
	 * @param UriInterface    $uri           The request URI object
	 * @param Headers         $headers       The request headers collection
	 * @param array           $cookies       The request cookies collection
	 * @param array           $serverParams  The server environment variables
	 * @param StreamInterface $body          The request body object
	 * @param array           $uploadedFiles The request uploadedFiles collection
	 *
	 * @throws \InvalidArgumentException on invalid HTTP method
	 */
	public function __construct(
		$method,
		UriInterface $uri,
		Headers $headers,
		array $cookies,
		array $serverParams,
		StreamInterface $body,
		array $uploadedFiles = []
	) {
		$this->method         = $this->filterMethod($method);
		$this->originalMethod = $this->method;
		$this->uri            = $uri;
		$this->headers        = $headers;
		$this->cookies        = $cookies;
		$this->serverParams   = $serverParams;
		$this->attributes     = new Collection();
		$this->body           = $body;
		$this->uploadedFiles  = $uploadedFiles;

		if ($this->method === 'POST') {
			$allowed = SettingsManager::get('oz.config', 'OZ_API_ALLOW_REAL_METHOD_HEADER');

			if ($allowed) {
				$realMethodHeaderName = SettingsManager::get('oz.config', 'OZ_API_REAL_METHOD_HEADER_NAME');
				$realMethodHeaderName = \strtolower(\str_replace('_', '-', $realMethodHeaderName));
				$realMethod           = $this->getHeaderLine($realMethodHeaderName);

				if (!empty($realMethod)) {
					$this->method = $this->filterMethod($realMethod);
				}
			}
		}

		if (isset($serverParams['SERVER_PROTOCOL'])) {
			$this->protocolVersion = \str_replace('HTTP/', '', $serverParams['SERVER_PROTOCOL']);
		}

		if (!$this->headers->has('Host') || $this->uri->getHost() !== '') {
			$this->headers->set('Host', $this->uri->getHost());
		}

		$this->registerMediaTypeParser('application/json', function ($input) {
			$result = \json_decode($input, true);

			if (\json_last_error() !== \JSON_ERROR_NONE || !\is_array($result)) {
				return null;
			}

			return $result;
		});

		$this->registerMediaTypeParser('application/xml', function ($input) {
			$backup        = \libxml_disable_entity_loader(true);
			$backup_errors = \libxml_use_internal_errors(true);
			$result        = \simplexml_load_string($input);
			\libxml_disable_entity_loader($backup);
			\libxml_clear_errors();
			\libxml_use_internal_errors($backup_errors);

			if ($result === false) {
				return null;
			}

			return $result;
		});

		$this->registerMediaTypeParser('text/xml', function ($input) {
			$backup        = \libxml_disable_entity_loader(true);
			$backup_errors = \libxml_use_internal_errors(true);
			$result        = \simplexml_load_string($input);
			\libxml_disable_entity_loader($backup);
			\libxml_clear_errors();
			\libxml_use_internal_errors($backup_errors);

			if ($result === false) {
				return null;
			}

			return $result;
		});

		$this->registerMediaTypeParser('application/x-www-form-urlencoded', function ($input) {
			\parse_str($input, $data);

			return $data;
		});

		// if the request had an invalid method, we can throw it now
		if (isset($e) && $e instanceof InvalidArgumentException) {
			throw $e;
		}
	}

	/**
	 * Register media type parser.
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * @param string   $mediaType a HTTP media type (excluding content-type
	 *                            params)
	 * @param callable $callable  a callable that returns parsed contents for
	 *                            media type
	 */
	public function registerMediaTypeParser($mediaType, callable $callable)
	{
		if ($callable instanceof Closure) {
			$callable = $callable->bindTo($this);
		}
		$this->bodyParsers[(string) $mediaType] = $callable;
	}

	/**
	 * Gets request media type, if known.
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * @return null|string The request media type, minus content-type params
	 */
	public function getMediaType()
	{
		$contentType = $this->getContentType();

		if ($contentType) {
			$contentTypeParts = \preg_split('/\s*[;,]\s*/', $contentType);

			return \strtolower($contentTypeParts[0]);
		}

		return null;
	}

	/**
	 * Gets request content type.
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * @return null|string The request content type, if known
	 */
	public function getContentType()
	{
		$result = $this->getHeader('Content-Type');

		return $result ? $result[0] : null;
	}

	/**
	 * Returns an instance with the specified body parameters.
	 *
	 * These MAY be injected during instantiation.
	 *
	 * If the request Content-Type is either application/x-www-form-urlencoded
	 * or multipart/form-data, and the request method is POST, use this method
	 * ONLY to inject the contents of $_POST.
	 *
	 * The data IS NOT REQUIRED to come from $_POST, but MUST be the results of
	 * deserializing the request body content. Deserialization/parsing returns
	 * structured data, and, as such, this method ONLY accepts arrays or objects,
	 * or a null value if nothing was available to parse.
	 *
	 * As an example, if content negotiation determines that the request data
	 * is a JSON payload, this method could be used to create a request
	 * instance with the deserialized parameters.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * updated body parameters.
	 *
	 * @param null|array|object $data The deserialized body data. This will
	 *                                typically be in an array or object.
	 *
	 * @throws \InvalidArgumentException if an unsupported argument type is
	 *                                   provided
	 *
	 * @return static
	 */
	public function withParsedBody($data)
	{
		if (null !== $data && !\is_object($data) && !\is_array($data)) {
			throw new InvalidArgumentException('Parsed body value must be an array, an object, or null');
		}

		$clone             = clone $this;
		$clone->bodyParsed = $data;

		return $clone;
	}

	/**
	 * Gets the original HTTP method (ignore override).
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * @return string
	 */
	public function getOriginalMethod()
	{
		return $this->originalMethod;
	}

	/**
	 * Is this a GET request?
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * @return bool
	 */
	public function isGet()
	{
		return $this->isMethod('GET');
	}

	/**
	 * Does this request use a given method?
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * @param string $method HTTP method
	 *
	 * @return bool
	 */
	public function isMethod($method)
	{
		return $this->getMethod() === $method;
	}

	/**
	 * Retrieves the HTTP method of the request.
	 *
	 * @return string returns the request method
	 */
	public function getMethod()
	{
		return $this->method;
	}

	/**
	 * Retrieve any parameters provided in the request body.
	 *
	 * If the request Content-Type is either application/x-www-form-urlencoded
	 * or multipart/form-data, and the request method is POST, this method MUST
	 * returns the contents of $_POST.
	 *
	 * Otherwise, this method may return any results of deserializing
	 * the request body content; as parsing returns structured content, the
	 * potential types MUST be arrays or objects only. A null value indicates
	 * the absence of body content.
	 *
	 * @throws \RuntimeException if the request body media type parser returns an invalid value
	 *
	 * @return null|array|object The deserialized body parameters, if any.
	 *                           These will typically be an array or object.
	 */
	public function getParsedBody()
	{
		if ($this->bodyParsed !== false) {
			return $this->bodyParsed;
		}

		if (!$this->body) {
			return null;
		}

		$mediaType = $this->getMediaType();

		// look for a media type with a structured syntax suffix (RFC 6839)
		$parts = \explode('+', $mediaType);

		if (\count($parts) >= 2) {
			$mediaType = 'application/' . $parts[\count($parts) - 1];
		}

		if (isset($this->bodyParsers[$mediaType]) === true) {
			$body   = (string) $this->getBody();
			$parsed = $this->bodyParsers[$mediaType]($body);

			if (null !== $parsed && !\is_object($parsed) && !\is_array($parsed)) {
				throw new RuntimeException(
					'Request body media type parser return value must be an array, an object, or null'
				);
			}
			$this->bodyParsed = $parsed;

			return $this->bodyParsed;
		}

		return null;
	}

	/**
	 * Returns an instance with the provided HTTP method.
	 *
	 * While HTTP method names are typically all uppercase characters, HTTP
	 * method names are case-sensitive and thus implementations SHOULD NOT
	 * modify the given string.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * changed request method.
	 *
	 * @param string $method case-sensitive method
	 *
	 * @throws \InvalidArgumentException for invalid HTTP methods
	 *
	 * @return static
	 */
	public function withMethod($method)
	{
		$method                = $this->filterMethod($method);
		$clone                 = clone $this;
		$clone->originalMethod = $method;
		$clone->method         = $method;

		return $clone;
	}

	/**
	 * Retrieves the message's request target.
	 *
	 * Retrieves the message's request-target either as it will appear (for
	 * clients), as it appeared at request (for servers), or as it was
	 * specified for the instance (see withRequestTarget()).
	 *
	 * In most cases, this will be the origin-form of the composed URI,
	 * unless a value was provided to the concrete implementation (see
	 * withRequestTarget() below).
	 *
	 * If no URI is available, and no request-target has been specifically
	 * provided, this method MUST return the string "/".
	 *
	 * @return string
	 */
	public function getRequestTarget()
	{
		if ($this->requestTarget) {
			return $this->requestTarget;
		}

		if ($this->uri === null) {
			return '/';
		}

		$basePath = $this->uri->getBasePath();
		$path     = $this->uri->getPath();
		$path     = $basePath . '/' . \ltrim($path, '/');

		$query = $this->uri->getQuery();

		if ($query) {
			$path .= '?' . $query;
		}
		$this->requestTarget = $path;

		return $this->requestTarget;
	}

	/*
	 * URI
	 */

	/**
	 * Returns an instance with the specific request-target.
	 *
	 * If the request needs a non-origin-form request-target — e.g., for
	 * specifying an absolute-form, authority-form, or asterisk-form —
	 * this method may be used to create an instance with the specified
	 * request-target, verbatim.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * changed request target.
	 *
	 * @link http://tools.ietf.org/html/rfc7230#section-2.7 (for the various
	 *     request-target forms allowed in request messages)
	 *
	 * @param mixed $requestTarget
	 *
	 * @throws \InvalidArgumentException if the request target is invalid
	 *
	 * @return static
	 */
	public function withRequestTarget($requestTarget)
	{
		if (\preg_match('#\s#', $requestTarget)) {
			throw new InvalidArgumentException(
				'Invalid request target provided; must be a string and cannot contain whitespace'
			);
		}
		$clone                = clone $this;
		$clone->requestTarget = $requestTarget;

		return $clone;
	}

	/**
	 * Retrieves the URI instance.
	 *
	 * This method MUST return a UriInterface instance.
	 *
	 * @link http://tools.ietf.org/html/rfc3986#section-4.3
	 *
	 * @return \OZONE\OZ\Http\Uri returns a UriInterface instance
	 *                            representing the URI of the request
	 */
	public function getUri()
	{
		return $this->uri;
	}

	/**
	 * Returns an instance with the provided URI.
	 *
	 * This method MUST update the Host header of the returned request by
	 * default if the URI contains a host component. If the URI does not
	 * contain a host component, any pre-existing Host header MUST be carried
	 * over to the returned request.
	 *
	 * You can opt-in to preserving the original state of the Host header by
	 * setting `$preserveHost` to `true`. When `$preserveHost` is set to
	 * `true`, this method interacts with the Host header in the following ways:
	 *
	 * - If the the Host header is missing or empty, and the new URI contains
	 *   a host component, this method MUST update the Host header in the returned
	 *   request.
	 * - If the Host header is missing or empty, and the new URI does not contain a
	 *   host component, this method MUST NOT update the Host header in the returned
	 *   request.
	 * - If a Host header is present and non-empty, this method MUST NOT update
	 *   the Host header in the returned request.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * new UriInterface instance.
	 *
	 * @link http://tools.ietf.org/html/rfc3986#section-4.3
	 *
	 * @param UriInterface $uri          new request URI to use
	 * @param bool         $preserveHost preserve the original state of the Host header
	 *
	 * @return static
	 */
	public function withUri(UriInterface $uri, $preserveHost = false)
	{
		$clone      = clone $this;
		$clone->uri = $uri;

		if (!$preserveHost) {
			if ($uri->getHost() !== '') {
				$clone->headers->set('Host', $uri->getHost());
			}
		} else {
			if ($uri->getHost() !== '' && (!$this->hasHeader('Host') || $this->getHeaderLine('Host') === '')) {
				$clone->headers->set('Host', $uri->getHost());
			}
		}

		return $clone;
	}

	/**
	 * Is this a POST request?
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * @return bool
	 */
	public function isPost()
	{
		return $this->isMethod('POST');
	}

	/**
	 * Is this a PUT request?
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * @return bool
	 */
	public function isPut()
	{
		return $this->isMethod('PUT');
	}

	/**
	 * Is this a PATCH request?
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * @return bool
	 */
	public function isPatch()
	{
		return $this->isMethod('PATCH');
	}

	/**
	 * Is this a DELETE request?
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * @return bool
	 */
	public function isDelete()
	{
		return $this->isMethod('DELETE');
	}

	/**
	 * Is this a HEAD request?
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * @return bool
	 */
	public function isHead()
	{
		return $this->isMethod('HEAD');
	}

	/**
	 * Is this a OPTIONS request?
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * @return bool
	 */
	public function isOptions()
	{
		return $this->isMethod('OPTIONS');
	}

	/*
	 * Cookies
	 */

	/**
	 * Is this an XHR request?
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * @return bool
	 */
	public function isXhr()
	{
		return $this->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
	}

	/**
	 * Gets request content character set, if known.
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * @return null|string
	 */
	public function getContentCharset()
	{
		$mediaTypeParams = $this->getMediaTypeParams();

		if (isset($mediaTypeParams['charset'])) {
			return $mediaTypeParams['charset'];
		}

		return null;
	}

	/**
	 * Gets request media type params, if known.
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * @return array
	 */
	public function getMediaTypeParams()
	{
		$contentType       = $this->getContentType();
		$contentTypeParams = [];

		if ($contentType) {
			$contentTypeParts       = \preg_split('/\s*[;,]\s*/', $contentType);
			$contentTypePartsLength = \count($contentTypeParts);

			for ($i = 1; $i < $contentTypePartsLength; $i++) {
				$paramParts                                     = \explode('=', $contentTypeParts[$i]);
				$contentTypeParams[\strtolower($paramParts[0])] = $paramParts[1];
			}
		}

		return $contentTypeParams;
	}

	/*
	 * Query Params
	 */

	/**
	 * Gets request content length, if known.
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * @return null|int
	 */
	public function getContentLength()
	{
		$result = $this->headers->get('Content-Length');

		return $result ? (int) $result[0] : null;
	}

	/**
	 * Fetches cookie value from cookies sent by the client to the server.
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * @param string $key     the attribute name
	 * @param mixed  $default default value to return if the attribute does not exist
	 *
	 * @return mixed
	 */
	public function getCookieParam($key, $default = null)
	{
		$cookies = $this->getCookieParams();
		$result  = $default;

		if (isset($cookies[$key])) {
			$result = $cookies[$key];
		}

		return $result;
	}

	/*
	 * File Params
	 */

	/**
	 * Retrieve cookies.
	 *
	 * Retrieves cookies sent by the client to the server.
	 *
	 * The data MUST be compatible with the structure of the $_COOKIE
	 * superglobal.
	 *
	 * @return array
	 */
	public function getCookieParams()
	{
		return $this->cookies;
	}

	/**
	 * Returns an instance with the specified cookies.
	 *
	 * The data IS NOT REQUIRED to come from the $_COOKIE superglobal, but MUST
	 * be compatible with the structure of $_COOKIE. Typically, this data will
	 * be injected at instantiation.
	 *
	 * This method MUST NOT update the related Cookie header of the request
	 * instance, nor related values in the server params.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * updated cookie values.
	 *
	 * @param array $cookies array of key/value pairs representing cookies
	 *
	 * @return static
	 */
	public function withCookieParams(array $cookies)
	{
		$clone          = clone $this;
		$clone->cookies = $cookies;

		return $clone;
	}

	/*
	 * Server Params
	 */

	/**
	 * Retrieve a server parameter.
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public function getServerParam($key, $default = null)
	{
		$serverParams = $this->getServerParams();

		return isset($serverParams[$key]) ? $serverParams[$key] : $default;
	}

	/**
	 * Retrieve server parameters.
	 *
	 * Retrieves data related to the incoming request environment,
	 * typically derived from PHP's $_SERVER superglobal. The data IS NOT
	 * REQUIRED to originate from $_SERVER.
	 *
	 * @return array
	 */
	public function getServerParams()
	{
		return $this->serverParams;
	}

	/*
	 * Attributes
	 */

	/**
	 * Retrieve attributes derived from the request.
	 *
	 * The request "attributes" may be used to allow injection of any
	 * parameters derived from the request: e.g., the results of path
	 * match operations; the results of decrypting cookies; the results of
	 * deserializing non-form-encoded message bodies; etc. Attributes
	 * will be application and request specific, and CAN be mutable.
	 *
	 * @return array attributes derived from the request
	 */
	public function getAttributes()
	{
		return $this->attributes->all();
	}

	/**
	 * Retrieve a single derived request attribute.
	 *
	 * Retrieves a single derived request attribute as described in
	 * getAttributes(). If the attribute has not been previously set, returns
	 * the default value as provided.
	 *
	 * This method obviates the need for a hasAttribute() method, as it allows
	 * specifying a default value to return if the attribute is not found.
	 *
	 * @param string $name    the attribute name
	 * @param mixed  $default default value to return if the attribute does not exist
	 *
	 * @return mixed
	 *
	 * @see getAttributes()
	 */
	public function getAttribute($name, $default = null)
	{
		return $this->attributes->get($name, $default);
	}

	/**
	 * Returns an instance with the specified derived request attribute.
	 *
	 * This method allows setting a single derived request attribute as
	 * described in getAttributes().
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * updated attribute.
	 *
	 * @param string $name  the attribute name
	 * @param mixed  $value the value of the attribute
	 *
	 * @return static
	 *
	 * @see getAttributes()
	 */
	public function withAttribute($name, $value)
	{
		$clone = clone $this;
		$clone->attributes->set($name, $value);

		return $clone;
	}

	/**
	 * Returns an instance that removes the specified derived request attribute.
	 *
	 * This method allows removing a single derived request attribute as
	 * described in getAttributes().
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that removes
	 * the attribute.
	 *
	 * @param string $name the attribute name
	 *
	 * @return static
	 *
	 * @see getAttributes()
	 */
	public function withoutAttribute($name)
	{
		$clone = clone $this;
		$clone->attributes->remove($name);

		return $clone;
	}

	/**
	 * Creates a new instance with the specified derived request attributes.
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * This method allows setting all new derived request attributes as
	 * described in getAttributes().
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return a new instance that has the
	 * updated attributes.
	 *
	 * @param array $attributes New attributes
	 *
	 * @return static
	 */
	public function withAttributes(array $attributes)
	{
		$clone             = clone $this;
		$clone->attributes = new Collection($attributes);

		return $clone;
	}

	/*
	 * Body
	 */

	/**
	 * Force Body to be parsed again.
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * @return $this
	 */
	public function reparseBody()
	{
		$this->bodyParsed = false;

		return $this;
	}

	/**
	 * Fetches request parameter value from body or query string, or uploded files (in that order).
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * @param string $key     the parameter key
	 * @param string $default the default value
	 *
	 * @return mixed the parameter value
	 */
	public function getFormField($key, $default = null)
	{
		$postParams = $this->getParsedBody();
		$getParams  = $this->getQueryParams();
		$files      = $this->getUploadedFiles();
		$result     = $default;

		if (\is_array($postParams) && isset($postParams[$key])) {
			$result = $postParams[$key];
		} elseif (\is_object($postParams) && \property_exists($postParams, $key)) {
			$result = $postParams->$key;
		} elseif (isset($getParams[$key])) {
			$result = $getParams[$key];
		} elseif (isset($files[$key])) {
			$result = $files[$key];
		}

		return $result;
	}

	/**
	 * Fetches associative array of body, query string parameters and uploaded files.
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * @param bool $includeFiles
	 *
	 * @return array
	 */
	public function getFormData($includeFiles = true)
	{
		$params     = $this->getQueryParams();
		$postParams = $this->getParsedBody();
		$files      = $this->getUploadedFiles();

		if ($postParams) {
			$params = \array_merge($params, (array) $postParams);
		}

		if (!$includeFiles) {
			return $params;
		}

		return \array_replace($params, $files);
	}

	/**
	 * Retrieve query string arguments.
	 *
	 * Retrieves the deserialized query string arguments, if any.
	 *
	 * Note: the query params might not be in sync with the URI or server
	 * params. If you need to ensure you are only getting the original
	 * values, you may need to parse the query string from `getUri()->getQuery()`
	 * or from the `QUERY_STRING` server param.
	 *
	 * @return array
	 */
	public function getQueryParams()
	{
		if (\is_array($this->queryParams)) {
			return $this->queryParams;
		}

		if ($this->uri === null) {
			return [];
		}

		\parse_str($this->uri->getQuery(), $this->queryParams); // <-- URL decodes data

		return $this->queryParams;
	}

	/**
	 * Returns an instance with the specified query string arguments.
	 *
	 * These values SHOULD remain immutable over the course of the incoming
	 * request. They MAY be injected during instantiation, such as from PHP's
	 * $_GET superglobal, or MAY be derived from some other value such as the
	 * URI. In cases where the arguments are parsed from the URI, the data
	 * MUST be compatible with what PHP's parse_str() would return for
	 * purposes of how duplicate query parameters are handled, and how nested
	 * sets are handled.
	 *
	 * Setting query string arguments MUST NOT change the URI stored by the
	 * request, nor the values in the server params.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * updated query string arguments.
	 *
	 * @param array $query array of query string arguments, typically from
	 *                     $_GET
	 *
	 * @return static
	 */
	public function withQueryParams(array $query)
	{
		$clone              = clone $this;
		$clone->queryParams = $query;

		return $clone;
	}

	/*
	 * Parameters (e.g., POST and GET data)
	 */

	/**
	 * Retrieve normalized file upload data.
	 *
	 * This method returns upload metadata in a normalized tree, with each leaf
	 * an instance of Psr\Http\Message\UploadedFileInterface.
	 *
	 * These values MAY be prepared from $_FILES or the message body during
	 * instantiation, or MAY be injected via withUploadedFiles().
	 *
	 * @return \OZONE\OZ\Http\UploadedFile[] an array tree of UploadedFileInterface instances; an empty
	 *                                       array MUST be returned if no data is present
	 */
	public function getUploadedFiles()
	{
		return $this->uploadedFiles;
	}

	/**
	 * Creates a new instance with the specified uploaded files.
	 *
	 * This method MUST be implemented in such a way as to retain the
	 * immutability of the message, and MUST return an instance that has the
	 * updated body parameters.
	 *
	 * @param array $uploadedFiles an array tree of UploadedFileInterface instances
	 *
	 * @throws \InvalidArgumentException if an invalid structure is provided
	 *
	 * @return static
	 */
	public function withUploadedFiles(array $uploadedFiles)
	{
		$clone                = clone $this;
		$clone->uploadedFiles = $uploadedFiles;

		return $clone;
	}

	/**
	 * Fetches parameter value from query string.
	 *
	 * Note: This method is not part of the PSR-7 standard.
	 *
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public function getQueryParam($key, $default = null)
	{
		$getParams = $this->getQueryParams();
		$result    = $default;

		if (isset($getParams[$key])) {
			$result = $getParams[$key];
		}

		return $result;
	}

	/**
	 * Validate the HTTP method
	 *
	 * @param string $method
	 *
	 * @throws \InvalidArgumentException on invalid HTTP method
	 *
	 * @return string
	 */
	protected function filterMethod($method)
	{
		if (!\is_string($method)) {
			throw new InvalidArgumentException(\sprintf(
				'Unsupported HTTP method; must be a string, received %s',
				(\is_object($method) ? \get_class($method) : \gettype($method))
			));
		}

		$method = \strtoupper($method);

		if (!\array_key_exists($method, $this->validMethods)) {
			throw new InvalidArgumentException(\sprintf("Invalid HTTP method '%s'", $method));
		}

		return $method;
	}

	/*
	 * Method
	 */

	/**
	 * Creates new HTTP request with data extracted from the application
	 * Environment object
	 *
	 * @param Environment $environment
	 *
	 * @return static
	 */
	public static function createFromEnvironment(Environment $environment)
	{
		$method        = $environment['REQUEST_METHOD'];
		$uri           = Uri::createFromEnvironment($environment);
		$headers       = Headers::createFromEnvironment($environment);
		$cookies       = Cookies::parseCookieHeaderString($headers->get('Cookie', [''])[0]);
		$serverParams  = $environment->all();
		$body          = new RequestBody();
		$uploadedFiles = UploadedFile::createFromEnvironment($environment);

		$request = new static($method, $uri, $headers, $cookies, $serverParams, $body, $uploadedFiles);

		if (
			$method === 'POST' &&
			\in_array($request->getMediaType(), ['application/x-www-form-urlencoded', 'multipart/form-data'])
		) {
			// parsed body must be $_POST
			$request = $request->withParsedBody($_POST);
		}

		return $request;
	}

	/**
	 * This method is applied to the cloned object
	 * after PHP performs an initial shallow-copy. This
	 * method completes a deep-copy by creating new objects
	 * for the cloned object's internal reference pointers.
	 */
	public function __clone()
	{
		$this->headers    = clone $this->headers;
		$this->attributes = clone $this->attributes;
		$this->body       = clone $this->body;
	}
}
