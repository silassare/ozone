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
 * Class Headers.
 */
class Headers extends Collection
{
	/**
	 * Special HTTP headers that do not have the "HTTP_" prefix.
	 *
	 * @var array
	 */
	protected static array $special = [
		'CONTENT_TYPE'    => 1,
		'CONTENT_LENGTH'  => 1,
		'PHP_AUTH_USER'   => 1,
		'PHP_AUTH_PW'     => 1,
		'PHP_AUTH_DIGEST' => 1,
		'AUTH_TYPE'       => 1,
	];

	/**
	 * {@inheritDoc}
	 *
	 * Returns array of HTTP header names and values.
	 * This method returns the _original_ header name
	 * as specified by the end user.
	 */
	public function all(): array
	{
		$all = parent::all();
		$out = [];

		foreach ($all as /* $key => */ $props) {
			$out[$props['originalKey']] = $props['value'];
		}

		return $out;
	}

	/**
	 * Gets HTTP header key as originally specified.
	 *
	 * @param string     $key     The case-insensitive header name
	 * @param null|mixed $default The default value if key does not exist
	 *
	 * @return null|string
	 */
	public function getOriginalKey(string $key, ?string $default = null): ?string
	{
		if ($this->has($key)) {
			return parent::get($this->normalizeKey($key))['originalKey'];
		}

		return $default;
	}

	/**
	 * {@inheritDoc}
	 */
	public function has(string $key): bool
	{
		return parent::has($this->normalizeKey($key));
	}

	/**
	 * Normalize header name.
	 *
	 * This method transforms header names into a
	 * normalized form. This is how we enable case-insensitive
	 * header names in the other methods in this class.
	 *
	 * @param string $key The case-insensitive header name
	 *
	 * @return string Normalized header name
	 */
	public function normalizeKey(string $key): string
	{
		$key = \strtolower(\str_replace('_', '-', $key));

		if (\str_starts_with($key, 'http-')) {
			$key = \substr($key, 5);
		} elseif (\str_starts_with($key, 'redirect-http-')) {
			// Apache prefixes any environment variables set via RewriteRule with REDIRECT_
			$key = \substr($key, 14);
		}

		return $key;
	}

	/**
	 * Adds HTTP header value.
	 *
	 * This method appends a header value. Unlike the set() method,
	 * this method _appends_ this new value to any values
	 * that already exist for this header name.
	 *
	 * @param string       $key   The case-insensitive header name
	 * @param array|string $value The new header value(s)
	 */
	public function add(string $key, array|string $value): void
	{
		$oldValues = $this->get($key, []);
		$newValues = \is_array($value) ? $value : [$value];
		$this->set($key, \array_merge($oldValues, \array_values($newValues)));
	}

	/**
	 * {@inheritDoc}
	 */
	public function get(string $key, mixed $default = null): mixed
	{
		if ($this->has($key)) {
			return parent::get($this->normalizeKey($key))['value'];
		}

		return $default;
	}

	/**
	 * {@inheritDoc}
	 *
	 * This method sets a header value. It replaces
	 * any values that may already exist for the header name.
	 */
	public function set(string $key, mixed $value): void
	{
		if (!\is_array($value)) {
			$value = [$value];
		}
		parent::set($this->normalizeKey($key), [
			'value'       => $value,
			'originalKey' => $key,
		]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function remove(string $key): void
	{
		parent::remove($this->normalizeKey($key));
	}

	/**
	 * Creates new headers collection with data extracted from
	 * the application Environment object.
	 *
	 * @param HTTPEnvironment $environment
	 *
	 * @return self
	 */
	public static function createFromEnvironment(HTTPEnvironment $environment): self
	{
		$data        = [];
		$environment = self::determineAuthorization($environment);

		foreach ($environment as $key => $value) {
			$key = \strtoupper($key);

			if (isset(static::$special[$key]) || \str_starts_with($key, 'HTTP_')) {
				if ('HTTP_CONTENT_LENGTH' !== $key) {
					$data[$key] = $value;
				}
			}
		}

		return new static($data);
	}

	/**
	 * If HTTP_AUTHORIZATION does not exist tries to get it from
	 * getallheaders() when available.
	 *
	 * @param HTTPEnvironment $environment
	 *
	 * @return HTTPEnvironment
	 */
	public static function determineAuthorization(HTTPEnvironment $environment): HTTPEnvironment
	{
		$authorization = $environment->get('HTTP_AUTHORIZATION');

		if (null === $authorization && \is_callable('getallheaders')) {
			$headers = getallheaders();
			$headers = \array_change_key_case($headers, \CASE_LOWER);

			if (isset($headers['authorization'])) {
				$environment->set('HTTP_AUTHORIZATION', $headers['authorization']);
			}
		}

		return $environment;
	}
}
