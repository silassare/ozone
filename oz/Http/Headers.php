<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Http;

/**
 * Headers
 *
 * This class represents a collection of HTTP headers
 * that is used in both the HTTP request and response objects.
 * It also enables header name case-insensitivity when
 * getting or setting a header value.
 *
 * Each HTTP header can have multiple values. This class
 * stores values into an array for each header name. When
 * you request a header value, you receive an array of values
 * for that header.
 */
class Headers extends Collection
{
	/**
	 * Special HTTP headers that do not have the "HTTP_" prefix
	 *
	 * @var array
	 */
	protected static $special = [
		'CONTENT_TYPE'    => 1,
		'CONTENT_LENGTH'  => 1,
		'PHP_AUTH_USER'   => 1,
		'PHP_AUTH_PW'     => 1,
		'PHP_AUTH_DIGEST' => 1,
		'AUTH_TYPE'       => 1,
	];

	/**
	 * Returns array of HTTP header names and values.
	 * This method returns the _original_ header name
	 * as specified by the end user.
	 *
	 * @return array
	 */
	public function all()
	{
		$all = parent::all();
		$out = [];

		foreach ($all as $key => $props) {
			$out[$props['originalKey']] = $props['value'];
		}

		return $out;
	}

	/**
	 * Gets HTTP header key as originally specified
	 *
	 * @param string $key     The case-insensitive header name
	 * @param mixed  $default The default value if key does not exist
	 *
	 * @return string
	 */
	public function getOriginalKey($key, $default = null)
	{
		if ($this->has($key)) {
			return parent::get($this->normalizeKey($key))['originalKey'];
		}

		return $default;
	}

	/**
	 * Does this collection have a given header?
	 *
	 * @param string $key The case-insensitive header name
	 *
	 * @return bool
	 */
	public function has($key)
	{
		return parent::has($this->normalizeKey($key));
	}

	/**
	 * Normalize header name
	 *
	 * This method transforms header names into a
	 * normalized form. This is how we enable case-insensitive
	 * header names in the other methods in this class.
	 *
	 * @param string $key The case-insensitive header name
	 *
	 * @return string Normalized header name
	 */
	public function normalizeKey($key)
	{
		$key = \strtr(\strtolower($key), '_', '-');

		if (\strpos($key, 'http-') === 0) {
			$key = \substr($key, 5);
		}

		return $key;
	}

	/**
	 * Adds HTTP header value
	 *
	 * This method appends a header value. Unlike the set() method,
	 * this method _appends_ this new value to any values
	 * that already exist for this header name.
	 *
	 * @param string       $key   The case-insensitive header name
	 * @param array|string $value The new header value(s)
	 */
	public function add($key, $value)
	{
		$oldValues = $this->get($key, []);
		$newValues = \is_array($value) ? $value : [$value];
		$this->set($key, \array_merge($oldValues, \array_values($newValues)));
	}

	/**
	 * Gets HTTP header value
	 *
	 * @param string $key     The case-insensitive header name
	 * @param mixed  $default The default value if key does not exist
	 *
	 * @return string[]
	 */
	public function get($key, $default = null)
	{
		if ($this->has($key)) {
			return parent::get($this->normalizeKey($key))['value'];
		}

		return $default;
	}

	/**
	 * Sets HTTP header value
	 *
	 * This method sets a header value. It replaces
	 * any values that may already exist for the header name.
	 *
	 * @param string $key   The case-insensitive header name
	 * @param mixed  $value The header value
	 */
	public function set($key, $value)
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
	 * Removes header from collection
	 *
	 * @param string $key The case-insensitive header name
	 */
	public function remove($key)
	{
		parent::remove($this->normalizeKey($key));
	}

	/**
	 * Creates new headers collection with data extracted from
	 * the application Environment object
	 *
	 * @param Environment $environment
	 *
	 * @return self
	 */
	public static function createFromEnvironment(Environment $environment)
	{
		$data        = [];
		$environment = self::determineAuthorization($environment);

		foreach ($environment as $key => $value) {
			$key = \strtoupper($key);

			if (isset(static::$special[$key]) || \strpos($key, 'HTTP_') === 0) {
				if ($key !== 'HTTP_CONTENT_LENGTH') {
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
	 * @param Environment $environment
	 *
	 * @return Environment
	 */
	public static function determineAuthorization(Environment $environment)
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
