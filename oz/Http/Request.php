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

namespace OZONE\OZ\Http;

use Closure;
use InvalidArgumentException;
use OZONE\OZ\Core\Configs;
use OZONE\OZ\Forms\FormData;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * Class Request.
 */
class Request extends Message implements ServerRequestInterface
{
	/**
	 * The request method.
	 */
	protected string $method;

	/**
	 * The original request method (ignoring override).
	 */
	protected string $originalMethod;

	/**
	 * The request URI object.
	 */
	protected Uri $uri;

	/**
	 * The request URI target (path + query string).
	 */
	protected string $requestTarget;

	/**
	 * The request query string params.
	 */
	protected array $queryParams;

	/**
	 * The request cookies.
	 */
	protected array $cookies;

	/**
	 * The server environment variables at the time the request was created.
	 */
	protected array $serverParams;

	/**
	 * The request attributes (route segment names and values).
	 */
	protected Collection $attributes;

	/**
	 * The request body parsed (if possible) into a PHP array or object.
	 */
	protected null|array|object $bodyParsed = null;

	/**
	 * List of request body parsers (e.g., url-encoded, JSON, XML, multipart).
	 *
	 * @var callable[]
	 */
	protected array $bodyParsers = [];

	/**
	 * List of uploaded files.
	 *
	 * @var \OZONE\OZ\Http\UploadedFile[]
	 */
	protected array $uploadedFiles;

	/**
	 * Valid request methods.
	 */
	protected static array $validMethods = [
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
	 * @throws InvalidArgumentException on invalid HTTP method
	 */
	public function __construct(
		string $method,
		UriInterface $uri,
		Headers $headers,
		array $cookies,
		array $serverParams,
		StreamInterface $body,
		array $uploadedFiles = []
	) {
		$this->method         = self::filterMethod($method);
		$this->originalMethod = $this->method;
		$this->uri            = $uri;
		$this->headers        = $headers;
		$this->cookies        = $cookies;
		$this->serverParams   = $serverParams;
		$this->attributes     = new Collection();
		$this->body           = $body;
		$this->uploadedFiles  = $uploadedFiles;

		if ('POST' === $this->method) {
			$allowed = Configs::get('oz.config', 'OZ_API_ALLOW_REAL_METHOD_HEADER');

			if ($allowed) {
				$realMethodHeaderName = Configs::get('oz.config', 'OZ_API_REAL_METHOD_HEADER_NAME');
				$realMethodHeaderName = \strtolower(\str_replace('_', '-', $realMethodHeaderName));
				$realMethod           = $this->getHeaderLine($realMethodHeaderName);

				if (!empty($realMethod)) {
					$this->method = self::filterMethod($realMethod);
				}
			}
		}

		if (isset($serverParams['SERVER_PROTOCOL'])) {
			$this->protocolVersion = \str_replace('HTTP/', '', $serverParams['SERVER_PROTOCOL']);
		}

		if (!$this->headers->has('Host') || '' !== $this->uri->getHost()) {
			$this->headers->set('Host', $this->uri->getHost());
		}

		$json_parser = function ($input) {
			try {
				$result = \json_decode($input, true, 512, \JSON_THROW_ON_ERROR);

				if (!\is_array($result)) {
					return $result;
				}
			} catch (\JsonException) {
			}

			return null;
		};

		$xml_parser = function ($input) {
			$backup        = (\PHP_VERSION_ID < 80000) ? \libxml_disable_entity_loader(true) : null;
			$backup_errors = \libxml_use_internal_errors(true);
			$result        = \simplexml_load_string($input);

			null !== $backup && \libxml_disable_entity_loader($backup);

			\libxml_clear_errors();
			\libxml_use_internal_errors($backup_errors);

			if (false === $result) {
				return null;
			}

			return $result;
		};

		$this->registerMediaTypeParser('application/json', $json_parser);
		$this->registerMediaTypeParser('application/xml', $xml_parser);
		$this->registerMediaTypeParser('text/xml', $xml_parser);

		$this->registerMediaTypeParser('application/x-www-form-urlencoded', function ($input) {
			\parse_str($input, $data);

			return $data;
		});
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

	/**
	 * Register media type parser.
	 *
	 * @param string   $mediaType a HTTP media type (excluding content-type
	 *                            params)
	 * @param callable $callable  a callable that returns parsed contents for
	 *                            media type
	 */
	public function registerMediaTypeParser(string $mediaType, callable $callable): void
	{
		$callable_t = Closure::fromCallable($callable)
			->bindTo($this);

		if ($callable_t) {
			$callable = $callable_t;
		}

		$this->bodyParsers[$mediaType] = $callable;
	}

	/**
	 * Gets request media type, if known.
	 *
	 * @return null|string The request media type, minus content-type params
	 */
	public function getMediaType(): ?string
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
	 * @return null|string The request content type, if known
	 */
	public function getContentType(): ?string
	{
		$result = $this->getHeader('Content-Type');

		return $result ? $result[0] : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function withParsedBody($data): self
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
	 * @return string
	 */
	public function getOriginalMethod(): string
	{
		return $this->originalMethod;
	}

	/**
	 * Is this a GET request?
	 *
	 * @return bool
	 */
	public function isGet(): bool
	{
		return $this->isMethod('GET');
	}

	/**
	 * Does this request use a given method?
	 *
	 * @param string $method HTTP method
	 *
	 * @return bool
	 */
	public function isMethod(string $method): bool
	{
		return $this->getMethod() === $method;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getMethod(): string
	{
		return $this->method;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getParsedBody(): object|array|null
	{
		if (null !== $this->bodyParsed) {
			return $this->bodyParsed;
		}

		if (empty($this->body)) {
			return null;
		}

		$mediaType = $this->getMediaType();

		// look for a media type with a structured syntax suffix (RFC 6839)
		$parts = \explode('+', $mediaType);

		if (\count($parts) >= 2) {
			$mediaType = 'application/' . $parts[\count($parts) - 1];
		}

		if (true === isset($this->bodyParsers[$mediaType])) {
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
	 * {@inheritDoc}
	 */
	public function withMethod($method): self
	{
		$method                = self::filterMethod($method);
		$clone                 = clone $this;
		$clone->originalMethod = $method;
		$clone->method         = $method;

		return $clone;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getRequestTarget(): string
	{
		if ($this->requestTarget) {
			return $this->requestTarget;
		}

		if (empty($this->uri)) {
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

	/**
	 * {@inheritDoc}
	 */
	public function withRequestTarget($requestTarget): self
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
	 * {@inheritDoc}
	 */
	public function getUri(): Uri
	{
		return $this->uri;
	}

	/**
	 * {@inheritDoc}
	 */
	public function withUri(UriInterface $uri, $preserveHost = false): self
	{
		$clone      = clone $this;
		$clone->uri = $uri;

		if ('' !== $uri->getHost()) {
			if (!$preserveHost) {
				$clone->headers->set('Host', $uri->getHost());
			} elseif (!$this->hasHeader('Host') || '' === $this->getHeaderLine('Host')) {
				$clone->headers->set('Host', $uri->getHost());
			}
		}

		return $clone;
	}

	/**
	 * Is this a POST request?
	 *
	 * @return bool
	 */
	public function isPost(): bool
	{
		return $this->isMethod('POST');
	}

	/**
	 * Is this a PUT request?
	 *
	 * @return bool
	 */
	public function isPut(): bool
	{
		return $this->isMethod('PUT');
	}

	/**
	 * Is this a PATCH request?
	 *
	 * @return bool
	 */
	public function isPatch(): bool
	{
		return $this->isMethod('PATCH');
	}

	/**
	 * Is this a DELETE request?
	 *
	 * @return bool
	 */
	public function isDelete(): bool
	{
		return $this->isMethod('DELETE');
	}

	/**
	 * Is this a HEAD request?
	 *
	 * @return bool
	 */
	public function isHead(): bool
	{
		return $this->isMethod('HEAD');
	}

	/**
	 * Is this a OPTIONS request?
	 *
	 * @return bool
	 */
	public function isOptions(): bool
	{
		return $this->isMethod('OPTIONS');
	}

	/**
	 * Is this an XHR request?
	 *
	 * @return bool
	 */
	public function isXhr(): bool
	{
		return 'XMLHttpRequest' === $this->getHeaderLine('X-Requested-With');
	}

	/**
	 * Gets request content character set, if known.
	 *
	 * @return null|string
	 */
	public function getContentCharset(): ?string
	{
		$mediaTypeParams = $this->getMediaTypeParams();

		return $mediaTypeParams['charset'] ?? null;
	}

	/**
	 * Gets request media type params, if known.
	 *
	 * @return array
	 */
	public function getMediaTypeParams(): array
	{
		$contentType       = $this->getContentType();
		$contentTypeParams = [];

		if ($contentType) {
			$contentTypeParts       = \preg_split('/\s*[;,]\s*/', $contentType);
			$contentTypePartsLength = \count($contentTypeParts);

			for ($i = 1; $i < $contentTypePartsLength; ++$i) {
				$paramParts                                     = \explode('=', $contentTypeParts[$i]);
				$contentTypeParams[\strtolower($paramParts[0])] = $paramParts[1];
			}
		}

		return $contentTypeParams;
	}

	/**
	 * Gets request content length, if known.
	 *
	 * @return null|int
	 */
	public function getContentLength(): ?int
	{
		$result = $this->headers->get('Content-Length');

		return $result ? (int) $result[0] : null;
	}

	/**
	 * Fetches cookie value from cookies sent by the client to the server.
	 *
	 * @param string     $key     the attribute name
	 * @param null|mixed $default default value to return if the attribute does not exist
	 *
	 * @return mixed
	 */
	public function getCookieParam(string $key, mixed $default = null): mixed
	{
		$cookies = $this->getCookieParams();

		return $cookies[$key] ?? $default;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getCookieParams(): array
	{
		return $this->cookies;
	}

	/**
	 * {@inheritDoc}
	 */
	public function withCookieParams(array $cookies): self
	{
		$clone          = clone $this;
		$clone->cookies = $cookies;

		return $clone;
	}

	/**
	 * Retrieve a server parameter.
	 *
	 * @param string     $key
	 * @param null|mixed $default
	 *
	 * @return mixed
	 */
	public function getServerParam(string $key, mixed $default = null): mixed
	{
		$serverParams = $this->getServerParams();

		return $serverParams[$key] ?? $default;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getServerParams(): array
	{
		return $this->serverParams;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAttributes(): array
	{
		return $this->attributes->all();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAttribute($name, $default = null): mixed
	{
		return $this->attributes->get($name, $default);
	}

	/**
	 * {@inheritDoc}
	 */
	public function withAttribute($name, $value): self
	{
		$clone = clone $this;
		$clone->attributes->set($name, $value);

		return $clone;
	}

	/**
	 * {@inheritDoc}
	 */
	public function withoutAttribute($name): self
	{
		$clone = clone $this;
		$clone->attributes->remove($name);

		return $clone;
	}

	/**
	 * Creates a new instance with the specified derived request attributes.
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
	public function withAttributes(array $attributes): self
	{
		$clone             = clone $this;
		$clone->attributes = new Collection($attributes);

		return $clone;
	}

	/**
	 * Force Body to be parsed again.
	 *
	 * @return $this
	 */
	public function reparseBody(): self
	{
		$this->bodyParsed = null;

		return $this;
	}

	/**
	 * Fetches request parameter value from body or query string, or uploded files (in that order).
	 *
	 * @param string      $key     the parameter key
	 * @param null|string $default the default value
	 *
	 * @return mixed the parameter value
	 */
	public function getFormField(string $key, string $default = null): mixed
	{
		$postParams = $this->getParsedBody();
		$getParams  = $this->getQueryParams();
		$files      = $this->getUploadedFiles();
		$result     = $default;

		if (\is_array($postParams) && isset($postParams[$key])) {
			$result = $postParams[$key];
		} elseif (\is_object($postParams) && \property_exists($postParams, $key)) {
			$result = $postParams->{$key};
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
	 * @param bool $includeFiles
	 *
	 * @return FormData
	 */
	public function getFormData(bool $includeFiles = true): FormData
	{
		$params     = $this->getQueryParams();
		$postParams = $this->getParsedBody();
		$files      = $this->getUploadedFiles();

		if ($postParams) {
			$params = \array_merge($params, (array) $postParams);
		}

		if ($includeFiles) {
			$params = \array_replace($params, $files);
		}

		return new FormData($params);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getQueryParams(): array
	{
		if (!empty($this->queryParams)) {
			return $this->queryParams;
		}

		if (empty($this->uri)) {
			return [];
		}

		\parse_str($this->uri->getQuery(), $this->queryParams); // <-- URL decodes data

		return $this->queryParams;
	}

	/**
	 * {@inheritDoc}
	 */
	public function withQueryParams(array $query): self
	{
		$clone              = clone $this;
		$clone->queryParams = $query;

		return $clone;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\OZ\Http\UploadedFile[]
	 */
	public function getUploadedFiles(): array
	{
		return $this->uploadedFiles;
	}

	/**
	 * {@inheritDoc}
	 */
	public function withUploadedFiles(array $uploadedFiles): self
	{
		$clone                = clone $this;
		$clone->uploadedFiles = $uploadedFiles;

		return $clone;
	}

	/**
	 * Fetches parameter value from query string.
	 *
	 * @param string     $key
	 * @param null|mixed $default
	 *
	 * @return mixed
	 */
	public function getQueryParam(string $key, mixed $default = null): mixed
	{
		$getParams = $this->getQueryParams();

		return $getParams[$key] ?? $default;
	}

	/**
	 * Creates new HTTP request with data extracted from the application
	 * Environment object.
	 *
	 * @param Environment $environment
	 *
	 * @return static
	 */
	public static function createFromEnvironment(Environment $environment): self
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
			'POST' === $method
			&& \in_array($request->getMediaType(), ['application/x-www-form-urlencoded', 'multipart/form-data'], true)
		) {
			// parsed body must be $_POST
			$request = $request->withParsedBody($_POST);
		}

		return $request;
	}

	/**
	 * Validate the HTTP method.
	 *
	 * @param string $method
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException on invalid HTTP method
	 */
	public static function filterMethod(string $method): string
	{
		$method = \strtoupper($method);

		if (!\array_key_exists($method, self::$validMethods)) {
			throw new InvalidArgumentException(\sprintf("Invalid HTTP method '%s'", $method));
		}

		return $method;
	}
}
