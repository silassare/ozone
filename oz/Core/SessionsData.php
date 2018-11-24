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


	use OZONE\OZ\Exceptions\RuntimeException;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class SessionsData
	{
		/**
		 * Checks the given session key.
		 *
		 * @param string $key the session key
		 *
		 * @return mixed
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 */
		private static function keyCheck($key)
		{
			$key_reg  = "#^(?:[a-zA-Z_][a-zA-Z0-9_]*)(?:\:[a-zA-Z0-9_]+)*$#";
			$max_deep = 5;

			if (!preg_match($key_reg, $key)) {
				throw new RuntimeException("session key '$key' not well formed, use something like 'group:key' ");
			}

			$route = explode(':', $key);

			if (count($route) > $max_deep) {
				throw new RuntimeException("session key '$key' is too deep, maximum deep is $max_deep");
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
		 * @param string     $key
		 * @param mixed      $value
		 * @param array|null &$data
		 *
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 */
		public static function set($key, $value, array &$data = null)
		{
			if (is_null($data)) {
				// when called before session start
				if (!isset($_SESSION)) {
					oz_logger("Session not started, unable to set -> $key -> {json_encode($value)}");
					return;
				}

				$data = &$_SESSION;
			}

			$parts   = self::keyCheck($key);
			$counter = count($parts);
			$next    = &$data;

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
		 * @param string     $key the session key
		 * @param array|null $data
		 *
		 * @return mixed
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 */
		public static function get($key, array $data = null)
		{
			if (is_null($data)) {
				// when called before session start
				if (!isset($_SESSION)) {
					return null;
				}

				$data = $_SESSION;
			}

			$parts   = self::keyCheck($key);
			$counter = count($parts);
			$result  = $data;

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
		 * @param string     $key the session key
		 * @param array|null &$data
		 *
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 */
		public static function remove($key, array &$data = null)
		{
			if (is_null($data)) {
				// when called before session start
				if (!isset($_SESSION)) {
					oz_logger("Session not started, unable to remove -> $key");
					return;
				}

				$data = &$_SESSION;
			}

			$parts   = self::keyCheck($key);
			$counter = count($parts);
			$next    = &$data;

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