<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Core;

use InvalidArgumentException;

final class SessionDataStore
{
	/**
	 * @var array
	 */
	private $store_data;

	/**
	 * SessionDataStore constructor.
	 *
	 * @param array $store
	 */
	public function __construct(array $store = [])
	{
		$this->store_data = $store;
	}

	/**
	 * SessionDataStore destructor.
	 */
	public function __destruct()
	{
		unset($this->store_data);
	}

	/**
	 * Gets the store data.
	 *
	 * @return array
	 */
	public function getStoreData()
	{
		return $this->store_data;
	}

	/**
	 * Sets the store data.
	 *
	 * @param array $data
	 *
	 * @return $this
	 */
	public function setStoreData(array $data)
	{
		$this->store_data = $data;

		return $this;
	}

	/**
	 * Sets value of the given key to the store.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return $this
	 */
	public function set($key, $value)
	{
		$parts   = self::keyCheck($key);
		$counter = \count($parts);
		$next    = &$this->store_data;

		foreach ($parts as $part) {
			$counter--;

			if ($counter && (!isset($next[$part]) || !\is_array($next[$part]))) {
				$next[$part] = [];
			}

			$next = &$next[$part];
		}

		$next = $value;

		return $this;
	}

	/**
	 * Gets the given key value from store.
	 *
	 * @param string $key
	 * @param mixed  $def
	 *
	 * @return mixed
	 */
	public function get($key, $def = null)
	{
		$parts   = self::keyCheck($key);
		$counter = \count($parts);
		$result  = $this->store_data;

		foreach ($parts as $part) {
			$result = self::getNext($result, $part);
			$counter--;

			if ($counter && !\is_array($result)) {
				$result = null;

				break;
			}
		}

		return null === $result ? $def : $result;
	}

	/**
	 * Removes value from the store.
	 *
	 * @param string $key
	 *
	 * @return $this
	 */
	public function remove($key)
	{
		$parts   = self::keyCheck($key);
		$counter = \count($parts);
		$next    = &$this->store_data;

		// the counter is useful for us to move until
		// we reach the last part

		foreach ($parts as $part) {
			$counter--;

			if ($counter && isset($next[$part]) && \is_array($next[$part])) {
				$next = &$next[$part];
			} elseif (!$counter && \array_key_exists($part, $next)) {
				unset($next[$part]);
			} else {
				break;
			}
		}

		return $this;
	}

	/**
	 * Clears the store.
	 *
	 * @return $this
	 */
	public function clear()
	{
		$this->store_data = [];

		return $this;
	}

	/**
	 * Checks the given key.
	 *
	 * @param string $key the session key
	 *
	 * @return array
	 */
	private static function keyCheck($key)
	{
		$key_reg  = "~^(?:[a-zA-Z_][a-zA-Z0-9_]*)(?:\.[a-zA-Z0-9_]+)*$~";
		$max_deep = 5;

		if (!\preg_match($key_reg, $key)) {
			throw new InvalidArgumentException(\sprintf(
				'Session key "%s" not well formed, use something like "%s"',
				$key,
				'group.key'
			));
		}

		$route = \explode('.', $key);

		if (\count($route) > $max_deep) {
			throw new InvalidArgumentException(\sprintf(
				'Session key "%s" is too deep, maximum deep is %s',
				$key,
				$max_deep
			));
		}

		return $route;
	}

	/**
	 * Gets the value of the next key.
	 *
	 * @param mixed  $source
	 * @param string $next_key
	 *
	 * @return mixed
	 */
	private static function getNext($source, $next_key)
	{
		if (\is_array($source) && isset($source[$next_key])) {
			return $source[$next_key];
		}

		return null;
	}
}
