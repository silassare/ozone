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

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;
use Stringable;

/**
 * Class Uri.
 */
class Uri implements UriInterface
{
	/**
	 * Uri scheme (without "://" suffix).
	 */
	protected string $scheme = '';

	/**
	 * Uri user.
	 */
	protected string $user = '';

	/**
	 * Uri password.
	 */
	protected string $password = '';

	/**
	 * Uri host.
	 */
	protected string $host = '';

	/**
	 * Uri port number.
	 */
	protected ?int $port;

	/**
	 * Uri base path.
	 */
	protected string $basePath = '';

	/**
	 * Uri path.
	 */
	protected string $path = '';

	/**
	 * Uri query string (without "?" prefix).
	 */
	protected string $query = '';

	/**
	 * Uri fragment string (without "#" prefix).
	 */
	protected string $fragment = '';

	/**
	 * Creates new Uri.
	 *
	 * @param string   $scheme   uri scheme
	 * @param string   $host     uri host
	 * @param null|int $port     uri port number
	 * @param string   $path     uri path
	 * @param string   $query    uri query string
	 * @param string   $fragment uri fragment
	 * @param string   $user     uri user
	 * @param string   $password uri password
	 */
	public function __construct(
		string $scheme,
		string $host,
		?int   $port = null,
		string $path = '/',
		string $query = '',
		string $fragment = '',
		string $user = '',
		string $password = ''
	)
	{
		$this->scheme   = $this->filterScheme($scheme);
		$this->host     = $host;
		$this->port     = $this->filterPort($port);
		$this->path     = empty($path) ? '/' : $this->filterPath($path);
		$this->query    = $this->filterQuery($query);
		$this->fragment = $this->filterQuery($fragment);
		$this->user     = $user;
		$this->password = $password;

		if ('/' !== $this->path[0]) {
			$this->path = '/' . $this->path;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function __toString(): string
	{
		$scheme    = $this->getScheme();
		$authority = $this->getAuthority();
		$basePath  = $this->getBasePath();
		$path      = $this->getPath();
		$query     = $this->getQuery();
		$fragment  = $this->getFragment();

		$path = $basePath . '/' . \ltrim($path, '/');

		return ($scheme ? $scheme . ':' : '')
			   . ($authority ? '//' . $authority : '')
			   . $path
			   . ($query ? '?' . $query : '')
			   . ($fragment ? '#' . $fragment : '');
	}

	/**
	 * {@inheritDoc}
	 */
	public function getScheme(): string
	{
		return $this->scheme;
	}

	/**
	 * {@inheritDoc}
	 */
	public function withScheme($scheme): self
	{
		$scheme        = $this->filterScheme($scheme);
		$clone         = clone $this;
		$clone->scheme = $scheme;

		return $clone;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAuthority(): string
	{
		$userInfo = $this->getUserInfo();
		$host     = $this->getHost();
		$port     = $this->getPort();

		return ($userInfo ? $userInfo . '@' : '') . $host . (null !== $port ? ':' . $port : '');
	}

	/**
	 * Sets base path.
	 *
	 * @param string $basePath
	 *
	 * @return self
	 */
	public function withBasePath(string $basePath): self
	{
		if (!empty($basePath)) {
			$basePath = '/' . \trim($basePath, '/'); // <-- Trim on both sides
		}
		$clone = clone $this;

		if ('/' !== $basePath) {
			$clone->basePath = $this->filterPath($basePath);
		}

		return $clone;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getUserInfo(): string
	{
		return $this->user . ($this->password ? ':' . $this->password : '');
	}

	/**
	 * Returns the fully qualified base URL.
	 *
	 * Note that this method never includes a trailing /
	 *
	 * This method is not part of PSR-7.
	 *
	 * @return string
	 */
	public function getBaseUrl(): string
	{
		$scheme    = $this->getScheme();
		$authority = $this->getAuthority();
		$basePath  = $this->getBasePath();

		if ($authority && !\str_starts_with($basePath, '/')) {
			$basePath = '/' . $basePath;
		}

		return ($scheme ? $scheme . ':' : '')
			   . ($authority ? '//' . $authority : '')
			   . \rtrim($basePath, '/');
	}

	/**
	 * {@inheritDoc}
	 */
	public function withUserInfo($user, $password = null): self
	{
		$clone           = clone $this;
		$clone->user     = $user;
		$clone->password = $password ?: '';

		return $clone;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getHost(): string
	{
		return $this->host;
	}

	/**
	 * {@inheritDoc}
	 */
	public function withHost($host): self
	{
		$clone       = clone $this;
		$clone->host = $host;

		return $clone;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPort(): ?int
	{
		return $this->port && !$this->hasStandardPort() ? $this->port : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function withPort($port): self
	{
		$port        = $this->filterPort($port);
		$clone       = clone $this;
		$clone->port = $port;

		return $clone;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPath(): string
	{
		return $this->path;
	}

	/**
	 * {@inheritDoc}
	 */
	public function withPath($path, bool $preserve_base_path = false): self
	{
		$clone       = clone $this;
		$clone->path = $this->filterPath($path);

		// if the path is absolute, then clear basePath
		if (false === $preserve_base_path && \str_starts_with($path, '/')) {
			$clone->basePath = '';
		}

		return $clone;
	}

	/**
	 * Retrieve the base path segment of the URI.
	 *
	 * This method MUST return a string; if no path is present it MUST return
	 * an empty string.
	 *
	 * @return string the base path segment of the URI
	 */
	public function getBasePath(): string
	{
		return $this->basePath;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getQuery(): string
	{
		return $this->query;
	}

	/**
	 * {@inheritDoc}
	 */
	public function withQuery($query): self
	{
		if (!\is_string($query) && !\method_exists($query, '__toString')) {
			throw new InvalidArgumentException('Uri query must be a string');
		}
		$query        = \ltrim((string)$query, '?');
		$clone        = clone $this;
		$clone->query = $this->filterQuery($query);

		return $clone;
	}

	/**
	 * Returns an instance with the specified query string.
	 *
	 * @param array $query
	 *
	 * @return self a new instance with the specified query string
	 */
	public function withQueryArray(array $query): self
	{
		return $this->withQuery(\http_build_query($query, '', '&', \PHP_QUERY_RFC3986));
	}

	/**
	 * {@inheritDoc}
	 */
	public function getFragment(): string
	{
		return $this->fragment;
	}

	/**
	 * {@inheritDoc}
	 */
	public function withFragment($fragment): self
	{
		if (!\is_string($fragment) && !\method_exists($fragment, '__toString')) {
			throw new InvalidArgumentException('Uri fragment must be a string');
		}
		$fragment        = \ltrim($fragment, '#');
		$clone           = clone $this;
		$clone->fragment = $this->filterQuery($fragment);

		return $clone;
	}

	/**
	 * Creates new Uri from string.
	 *
	 * @param string|Stringable $uri Complete Uri string
	 *                               (i.e., https://user:pass@host:443/path?query).
	 *
	 * @return self
	 */
	public static function createFromString(string|Stringable $uri): self
	{
		if (!\is_string($uri) && !\method_exists($uri, '__toString')) {
			throw new InvalidArgumentException('Uri must be a string');
		}

		$parts    = \parse_url($uri);
		$scheme   = $parts['scheme'] ?? '';
		$user     = $parts['user'] ?? '';
		$pass     = $parts['pass'] ?? '';
		$host     = $parts['host'] ?? '';
		$port     = $parts['port'] ?? null;
		$path     = $parts['path'] ?? '';
		$query    = $parts['query'] ?? '';
		$fragment = $parts['fragment'] ?? '';

		return new static($scheme, $host, $port, $path, $query, $fragment, $user, $pass);
	}

	/**
	 * Creates new Uri from environment.
	 *
	 * @param Environment $env
	 *
	 * @return self
	 */
	public static function createFromEnvironment(Environment $env): self
	{
		// Scheme
		$isSecure = $env->get('HTTPS');
		$scheme   = (empty($isSecure) || 'off' === $isSecure) ? 'http' : 'https';

		// Authority: Username and password
		$username = $env->get('PHP_AUTH_USER', '');
		$password = $env->get('PHP_AUTH_PW', '');

		// Authority: Host
		if ($env->has('HTTP_HOST')) {
			$host = $env->get('HTTP_HOST');
		} else {
			$host = $env->get('SERVER_NAME');
		}

		// Authority: Port
		$port = (int)$env->get('SERVER_PORT', 80);

		if (\preg_match('~^(\[[a-fA-F0-9:.]+])(:\d+)?\z~', $host, $matches)) {
			$host = $matches[1];

			if ($matches[2]) {
				$port = (int)\substr($matches[2], 1);
			}
		} else {
			$pos = \strpos($host, ':');

			if (false !== $pos) {
				$port = (int)\substr($host, $pos + 1);
				$host = \strstr($host, ':', true);
			}
		}

		// Path
		// parse_url() requires a full URL. As we don't extract the domain name or scheme,
		// we use a stand-in.
		$mock_domain       = 'https://example.com';
		$requestScriptName = \parse_url($mock_domain . $env->get('SCRIPT_NAME'), \PHP_URL_PATH);
		$requestScriptDir  = \dirname($requestScriptName);
		$requestUri        = \parse_url($mock_domain . $env->get('REQUEST_URI'), \PHP_URL_PATH);

		$basePath    = '';
		$virtualPath = $requestUri;

		if (0 === \stripos($requestUri, $requestScriptName)) {
			$basePath = ($requestUri === $requestScriptName) ? $requestScriptDir : $requestScriptName;
		} elseif ('/' !== $requestScriptDir && 0 === \stripos($requestUri, $requestScriptDir)) {
			$basePath = $requestScriptDir;
		}

		if ($basePath) {
			$virtualPath = \ltrim(\substr($requestUri, \strlen($basePath)), '/');
		}

		// Query string
		$queryString = $env->get('QUERY_STRING', '');

		if ('' === $queryString) {
			$queryString = \parse_url($mock_domain . $env->get('REQUEST_URI'), \PHP_URL_QUERY) ?? '';
		}

		// Fragment
		$fragment = '';

		// Builds Uri
		$uri = new static($scheme, $host, $port, $virtualPath, $queryString, $fragment, $username, $password);

		if ($basePath) {
			$uri = $uri->withBasePath($basePath);
		}

		return $uri;
	}

	/**
	 * Filters Uri scheme.
	 *
	 * @param string|Stringable $scheme raw Uri scheme
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException if Uri scheme is not "", "https", or "http"
	 * @throws InvalidArgumentException if the Uri scheme is not a string
	 */
	protected function filterScheme(string|Stringable $scheme): string
	{
		static $valid = [
			''      => true,
			'https' => true,
			'http'  => true,
		];

		if (!\is_string($scheme) && !\method_exists($scheme, '__toString')) {
			throw new InvalidArgumentException('Uri scheme must be a string');
		}

		$scheme = \str_replace('://', '', \strtolower($scheme));

		if (!isset($valid[$scheme])) {
			throw new InvalidArgumentException('Uri scheme must be one of: "", "https", "http"');
		}

		return $scheme;
	}

	/**
	 * Filters Uri port.
	 *
	 * @param null|int $port the Uri port number
	 *
	 * @return null|int
	 *
	 * @throws InvalidArgumentException if the port is invalid
	 */
	protected function filterPort(?int $port): ?int
	{
		if (null === $port || ($port >= 1 && $port <= 65535)) {
			return $port;
		}

		throw new InvalidArgumentException('Uri port must be null or an integer between 1 and 65535 (inclusive)');
	}

	/**
	 * Filters Uri path.
	 *
	 * This method percent-encodes all reserved
	 * characters in the provided path string. This method
	 * will NOT double-encode characters that are already
	 * percent-encoded.
	 *
	 * @param string $path the raw uri path
	 *
	 * @return string the RFC 3986 percent-encoded uri path
	 *
	 * @see   http://www.faqs.org/rfcs/rfc3986.html
	 */
	protected function filterPath(string $path): string
	{
		return \preg_replace_callback(
			'~[^a-zA-Z0-9_\-.\~:@&=+$,/;%]+|%(?![A-Fa-f0-9]{2})~',
			static function ($match) {
				return \rawurlencode($match[0]);
			},
			$path
		);
	}

	/**
	 * Filters the query string or fragment of a URI.
	 *
	 * @param string $query the raw uri query string
	 *
	 * @return string the percent-encoded query string
	 */
	protected function filterQuery(string $query): string
	{
		return \preg_replace_callback(
			'~[^a-zA-Z0-9_\-.\~!\$&\'()*+,;=%:@/?]+|%(?![A-Fa-f0-9]{2})~',
			static function ($match) {
				return \rawurlencode($match[0]);
			},
			$query
		);
	}

	/**
	 * Does this Uri use a standard port?
	 *
	 * @return bool
	 */
	protected function hasStandardPort(): bool
	{
		return ('http' === $this->scheme && 80 === $this->port) || ('https' === $this->scheme && 443 === $this->port);
	}
}
