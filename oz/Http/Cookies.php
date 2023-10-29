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

/**
 * Class Cookies.
 */
class Cookies
{
	/**
	 * Cookies from HTTP request.
	 */
	protected array $request_cookies = [];

	/**
	 * Cookies for HTTP response.
	 */
	protected array $response_cookies = [];

	/**
	 * Default cookie properties.
	 */
	protected array $defaults_properties = [
		'value'    => '',
		'domain'   => null,
		'hostonly' => null,
		'path'     => null,
		'expires'  => null,
		'secure'   => false,
		'httponly' => false,
		'samesite' => null,
	];

	/**
	 * Creates new cookies helper.
	 *
	 * @param array $cookies
	 */
	public function __construct(array $cookies = [])
	{
		$this->request_cookies = $cookies;
	}

	/**
	 * Sets default cookie properties.
	 *
	 * @param array $properties
	 *
	 * @return \OZONE\Core\Http\Cookies
	 */
	public function setDefaultsProperties(array $properties): self
	{
		$this->defaults_properties = \array_replace($this->defaults_properties, $properties);

		return $this;
	}

	/**
	 * Gets request cookie.
	 *
	 * @param string     $name    Cookie name
	 * @param null|mixed $default Cookie default value
	 *
	 * @return mixed Cookie value if present, else default
	 */
	public function get(string $name, mixed $default = null): mixed
	{
		return $this->request_cookies[$name] ?? $default;
	}

	/**
	 * Sets response cookie.
	 *
	 * @param string       $name  Cookie name
	 * @param array|string $value Cookie value, or cookie properties
	 *
	 * @return \OZONE\Core\Http\Cookies
	 */
	public function set(string $name, array|string $value): self
	{
		if (!\is_array($value)) {
			$value = ['value' => $value];
		}
		$this->response_cookies[$name] = \array_replace($this->defaults_properties, $value);

		return $this;
	}

	/**
	 * Converts to `Set-Cookie` headers.
	 *
	 * @return string[]
	 */
	public function toHeaders(): array
	{
		$headers = [];

		foreach ($this->response_cookies as $name => $properties) {
			$headers[] = $this->toHeader($name, $properties);
		}

		return $headers;
	}

	/**
	 * Parse HTTP request `Cookie:` header and extract
	 * into a PHP associative array.
	 *
	 * @param string $header The raw HTTP request `Cookie:` header
	 *
	 * @return array Associative array of cookie names and values
	 *
	 * @throws InvalidArgumentException if the cookie data cannot be parsed
	 */
	public static function parseCookieHeaderString(string $header): array
	{
		$header  = \rtrim($header, "\r\n");
		$pieces  = \preg_split('#;\s*#', $header);
		$cookies = [];

		foreach ($pieces as $cookie) {
			$cookie = \explode('=', $cookie);

			if (2 === \count($cookie)) {
				$key   = \urldecode($cookie[0]);
				$value = \urldecode($cookie[1]);

				if (!isset($cookies[$key])) {
					$cookies[$key] = $value;
				}
			}
		}

		return $cookies;
	}

	/**
	 * Converts to `Set-Cookie` header.
	 *
	 * @param string $name       Cookie name
	 * @param array  $properties Cookie properties
	 *
	 * @return string
	 */
	protected function toHeader(string $name, array $properties): string
	{
		$result = \urlencode($name) . '=' . \urlencode($properties['value']);

		if (isset($properties['domain'])) {
			$result .= '; domain=' . $properties['domain'];
		}

		if (isset($properties['path'])) {
			$result .= '; path=' . $properties['path'];
		}

		if (isset($properties['expires'])) {
			if (\is_string($properties['expires'])) {
				$timestamp = \strtotime($properties['expires']);
			} else {
				$timestamp = (int) $properties['expires'];
			}

			if (0 !== $timestamp) {
				$result .= '; expires=' . \gmdate('D, d-M-Y H:i:s e', $timestamp);
			}
		}

		if (isset($properties['secure']) && $properties['secure']) {
			$result .= '; secure';
		}

		if (isset($properties['hostonly']) && $properties['hostonly']) {
			$result .= '; HostOnly';
		}

		if (isset($properties['httponly']) && $properties['httponly']) {
			$result .= '; HttpOnly';
		}

		if (
			isset($properties['samesite']) && \in_array(\strtolower($properties['samesite']), [
				'none',
				'lax',
				'strict',
			], true)
		) {
			$result .= '; SameSite=' . $properties['samesite'];
		}

		return $result;
	}
}
