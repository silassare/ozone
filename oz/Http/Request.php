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

namespace OZONE\Core\Http;

use InvalidArgumentException;
use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Forms\FormData;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

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
	protected array $queryParams = [];

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
	protected array|object|null $bodyParsed = null;

	/**
	 * The request body parsers, by media type.
	 */
	protected RequestBodyParser $body_parser;

	/**
	 * List of uploaded files.
	 *
	 * @var UploadedFile[]
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
		$this->body_parser    = new RequestBodyParser();
		$this->method         = MethodOverride::resolve(
			$this->method,
			$this->getHeaderLine(MethodOverride::headerName())
		);

		if (isset($serverParams['SERVER_PROTOCOL'])) {
			$this->protocolVersion = \str_replace('HTTP/', '', $serverParams['SERVER_PROTOCOL']);
		}

		if (!$this->headers->has('Host') || '' !== $this->uri->getHost()) {
			$this->headers->set('Host', $this->uri->getHost());
		}
	}

	/**
	 * This method is applied to the cloned object
	 * after PHP performs an initial shallow-copy. This
	 * method completes a deep-copy by creating new objects
	 * for the cloned object's internal reference pointers.
	 */
	public function __clone()
	{
		$this->headers     = clone $this->headers;
		$this->attributes  = clone $this->attributes;
		$this->body        = clone $this->body;
		$this->body_parser = clone $this->body_parser;
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
		$this->body_parser->register($mediaType, $callable);
	}

	/**
	 * Gets the parsed `Content-Type`, if any.
	 */
	public function mediaType(): ?MediaType
	{
		return MediaType::fromContentType($this->getContentType());
	}

	/**
	 * Gets request media type, if known.
	 *
	 * @return null|string The request media type, minus content-type params
	 */
	public function getMediaType(): ?string
	{
		return $this->mediaType()?->type;
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
	#[Override]
	public function withParsedBody($data): static
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
	#[Override]
	public function getMethod(): string
	{
		return $this->method;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getParsedBody(): array|object|null
	{
		if (null === $this->bodyParsed) {
			$this->bodyParsed = $this->body_parser->parse($this->mediaType(), (string) $this->getBody());
		}

		return $this->bodyParsed;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function withMethod(string $method): static
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
	#[Override]
	public function getRequestTarget(): string
	{
		if ($this->requestTarget) {
			return $this->requestTarget;
		}

		if (empty((string) $this->uri)) {
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
	#[Override]
	public function withRequestTarget(string $requestTarget): static
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
	#[Override]
	public function getUri(): Uri
	{
		return $this->uri;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function withUri(UriInterface $uri, bool $preserveHost = false): static
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
		return $this->mediaType()?->param('charset');
	}

	/**
	 * Gets request media type params, if known.
	 *
	 * @return array<string, string>
	 */
	public function getMediaTypeParams(): array
	{
		return $this->mediaType()->params ?? [];
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
	#[Override]
	public function getCookieParams(): array
	{
		return $this->cookies;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function withCookieParams(array $cookies): static
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
	#[Override]
	public function getServerParams(): array
	{
		return $this->serverParams;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getAttributes(): array
	{
		return $this->attributes->all();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getAttribute(string $name, $default = null): mixed
	{
		return $this->attributes->get($name, $default);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function withAttribute(string $name, $value): static
	{
		$clone = clone $this;
		$clone->attributes->set($name, $value);

		return $clone;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function withoutAttribute(string $name): static
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
	public function withAttributes(array $attributes): static
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
	public function reparseBody(): static
	{
		$this->bodyParsed = null;

		return $this;
	}

	/**
	 * Fetches request parameter value from body or query string, or uploded files (in that order).
	 *
	 * @param string     $key     the parameter key
	 * @param null|mixed $default the default value
	 *
	 * @return mixed the parameter value
	 */
	public function getUnsafeFormField(string $key, mixed $default = null): mixed
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
	 * @param bool $include_files
	 *
	 * @return FormData
	 */
	public function getUnsafeFormData(bool $include_files = true): FormData
	{
		$params     = $this->getQueryParams();
		$postParams = $this->getParsedBody();
		$files      = $this->getUploadedFiles();

		if ($postParams) {
			$params = \array_merge($params, (array) $postParams);
		}

		if ($include_files) {
			$params = \array_replace($params, $files);
		}

		return new FormData($params);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getQueryParams(): array
	{
		if (!empty($this->queryParams)) {
			return $this->queryParams;
		}

		if (empty((string) $this->uri)) {
			return [];
		}

		\parse_str($this->uri->getQuery(), $this->queryParams); // <-- URL decodes data

		return $this->queryParams;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function withQueryParams(array $query): static
	{
		$clone              = clone $this;
		$clone->queryParams = $query;

		return $clone;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return UploadedFile[]
	 */
	#[Override]
	public function getUploadedFiles(): array
	{
		return $this->uploadedFiles;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function withUploadedFiles(array $uploadedFiles): static
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
	 * Creates new HTTP request with data extracted from the application HTTP Environment object.
	 *
	 * Without `$body`, the request is the one PHP received: its body is `php://input`, its files
	 * `$_FILES` and a form POST's parsed body `$_POST`. With `$body`, it came from a worker server
	 * that hands requests over as objects (RoadRunner, Swoole), so PHP's own request globals are not
	 * this request's and none of them is read: pass the server's parsed form and files instead.
	 *
	 * @param HTTPEnvironment      $environment
	 * @param null|StreamInterface $body           the request body, when PHP did not receive the request
	 * @param null|array           $parsed_body    the parsed form body, when the server parsed it
	 * @param null|array           $uploaded_files a normalized tree of {@see UploadedFile}
	 *
	 * @return static
	 */
	public static function createFromHTTPEnvironment(
		HTTPEnvironment $environment,
		?StreamInterface $body = null,
		?array $parsed_body = null,
		?array $uploaded_files = null
	): static {
		$from_php      = null === $body;
		$method        = $environment['REQUEST_METHOD'];
		$uri           = Uri::createFromEnvironment($environment);
		$headers       = Headers::createFromEnvironment($environment);
		$cookies       = Cookies::parseIncomingRequestCookieHeaderString($headers->get('Cookie', [''])[0]);
		$serverParams  = $environment->all();
		$body ??= new RequestBody();
		$uploadedFiles = $uploaded_files ?? ($from_php ? UploadedFile::createFromEnvironment($environment) : []);

		$request = new static($method, $uri, $headers, $cookies, $serverParams, $body, $uploadedFiles ?? []);

		if (null !== $parsed_body) {
			$request = $request->withParsedBody($parsed_body);
		} elseif (
			$from_php
			&& 'POST' === $method
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

	/**
	 * Checks whether this is a form discovery request.
	 *
	 * When enabled in settings, the client may send the configured header to indicate
	 * that instead of executing the route handler, the server should return the route's
	 * form bundle as JSON so the client can build the form UI dynamically.
	 *
	 * @return bool
	 */
	public function isFormDiscoveryRequest(): bool
	{
		$allowed = Settings::get('oz.request', 'OZ_FORM_DISCOVERY_HEADER_ALLOWED');

		if (!$allowed) {
			return false;
		}

		$header_name = Settings::get('oz.request', 'OZ_FORM_DISCOVERY_HEADER_NAME');

		return $this->getHeaderAsBool($header_name);
	}

	/**
	 * Checks whether this is a form resume request.
	 *
	 * Indicate that the client wants to bypass route handling and be in resume mode for a form.
	 */
	public function isFormResumeRequest(): bool
	{
		$header_name = Settings::get('oz.request', 'OZ_FORM_RESUME_HEADER_NAME');

		return $this->getHeaderAsBool($header_name);
	}
}
