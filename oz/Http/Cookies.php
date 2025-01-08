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

/**
 * Class Cookies.
 */
class Cookies
{
	/**
	 * Cookies for HTTP response.
	 *
	 * @var array<string, Cookie>
	 */
	protected array $response_cookies = [];

	/**
	 * Cookies for HTTP request.
	 *
	 * @var array<string, string>
	 */
	protected array $request_cookies = [];

	/**
	 * Creates new cookies helper.
	 */
	public function __construct(array $request_cookies = [])
	{
		$this->request_cookies = $request_cookies;
	}

	/**
	 * Gets the request cookies.
	 *
	 * @return array<string, string>
	 */
	public function getRequestCookies(): array
	{
		return $this->request_cookies;
	}

	/**
	 * Gets a cookie from the request.
	 *
	 * @param string $name The name of the cookie
	 */
	public function getRequestCookie(string $name): ?string
	{
		return $this->request_cookies[$name] ?? null;
	}

	/**
	 * Gets a cookie from the response.
	 *
	 * @param string $name The name of the cookie
	 */
	public function get(string $name): ?Cookie
	{
		return $this->response_cookies[$name] ?? null;
	}

	/**
	 * Gets all cookies from the response.
	 *
	 * @return Cookie[]
	 */
	public function getAll(): array
	{
		return $this->response_cookies;
	}

	/**
	 * Adds a cookie to the response.
	 *
	 * @param Cookie $cookie The cookie to set
	 *
	 * @return Cookies
	 */
	public function add(Cookie $cookie): self
	{
		$this->response_cookies[$cookie->name] = $cookie;

		return $this;
	}

	/**
	 * Converts to `Set-Cookie` headers.
	 *
	 * @return string[]
	 */
	public function toResponseHeaders(): array
	{
		$headers = [];

		foreach ($this->response_cookies as /* $name => */ $cookie) {
			$headers[] = (string) $cookie;
		}

		return $headers;
	}

	/**
	 * Parse HTTP request `Cookie:` header and extract
	 * into a PHP associative array.
	 *
	 * @param string $header The raw HTTP request `Cookie:` header
	 *
	 * @return array<string, string> Associative array of cookie names and values
	 */
	public static function parseIncomingRequestCookieHeaderString(string $header): array
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
}
