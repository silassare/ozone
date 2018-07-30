<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Core;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class SessionsData
	{
		/**
		 * Checks the given session key.
		 *
		 * @param string $key the session key
		 *
		 * @return mixed
		 * @throws \Exception
		 */
		private static function keyCheck($key)
		{
			$key_reg  = "#^(?:[a-zA-Z_][a-zA-Z0-9_]*)(?:\:[a-zA-Z0-9_]+)*$#";
			$max_deep = 5;

			if (!preg_match($key_reg, $key)) {
				throw new \Exception("session key '$key' not well formed, use something like 'group:key' ");
			}

			$route = explode(':', $key);

			if (count($route) > $max_deep) {
				throw new \Exception("session key '$key' is too deep, maximum deep is $max_deep");
			}

			return $route;
		}

		/**
		 * Gets the value of the next key.
		 *
		 * @param mixed  $source
		 * @param string $next_key
		 *
		 * @return mixed|null
		 */
		private static function getNext($source, $next_key)
		{
			if (is_array($source) AND isset($source[$next_key])) {
				return $source[$next_key];
			}

			return null;
		}

		/**
		 * Sets session value for a given key.
		 *
		 * @param string $key
		 * @param mixed  $value
		 *
		 * @throws \Exception
		 */
		public static function set($key, $value)
		{
			// when called before session start
			if (!isset($_SESSION)) return;

			$parts   = self::keyCheck($key);
			$counter = count($parts);
			$next    = &$_SESSION;

			foreach ($parts as $part) {
				$counter--;
				if ($counter AND (!isset($next[$part]) OR !is_array($next[$part]))) {
					$next[$part] = [];
				}

				$next = &$next[$part];
			}

			$next = $value;
		}

		/**
		 * Gets session value for a given key.
		 *
		 * @param string $key the session key
		 *
		 * @return mixed
		 * @throws \Exception
		 */
		public static function get($key)
		{
			// when called before session start
			if (!isset($_SESSION)) return null;

			$parts   = self::keyCheck($key);
			$counter = count($parts);
			$result  = $_SESSION;

			foreach ($parts as $part) {
				$result = self::getNext($result, $part);
				$counter--;

				if ($counter AND !is_array($result)) {
					$result = null;
					break;
				}
			}

			return $result;
		}

		/**
		 * Remove session value for a given key.
		 *
		 * @param string $key the session key
		 *
		 * @throws \Exception
		 */
		public static function remove($key)
		{
			// when called before session start
			if (!isset($_SESSION)) return;

			$parts   = self::keyCheck($key);
			$counter = count($parts);
			$next    = &$_SESSION;

			// the counter is useful for us to move until
			// we reach the last part

			foreach ($parts as $part) {
				$counter--;
				if ($counter AND isset($next[$part]) AND is_array($next[$part])) {
					$next = &$next[$part];
				} elseif (!$counter AND array_key_exists($part, $next)) {
					unset($next[$part]);
				} else {
					break;
				}
			}
		}
	}