<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Core;

	use OZONE\OZ\Utils\OZoneStr;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class OZoneKeyGen
	{

		/**
		 * @param string $name The 'oz.keygen.salt' settings salt key name.
		 *
		 * @return mixed|null
		 * @throws \OZONE\OZ\Exceptions\OZoneInternalError
		 */
		private static function getSalt($name)
		{
			return OZoneSettings::get('oz.keygen.salt', $name);
		}

		/**
		 * @param string $path The file path.
		 *
		 * @return string
		 * @throws \Exception    When the file doesn't exists
		 */
		public static function genFileKey($path)
		{
			if (!file_exists($path)) {
				throw new \Exception("can't generate file key for: $path");
			}

			// make sure to make differences between each cloned file key
			// if no, all clone will have the same fkey as the original file
			srand(microtime() * 100);

			$salt = self::getSalt('OZ_FKEY_GEN_SALT') . microtime() . rand(111111, 999999);
			$str  = md5_file($path) . $salt;

			return self::hashIt($str, 32);
		}

		/**
		 * hash string with a given hash string length
		 *
		 * @param string $string The string to hash
		 * @param int    $length The desired hash string length default 32
		 *
		 * @return string
		 * @throws \InvalidArgumentException
		 */
		public static function hashIt($string, $length = 32)
		{
			$accept = [32, 64];

			if (!in_array($length, $accept)) {
				$values = join($accept, ' , ');

				throw new \InvalidArgumentException("hash length argument shoud be on of this list: $values");
			}

			$string = hash('sha256', $string);

			if ($length === 32) {
				return md5($string);
			}

			return $string;
		}

		/**
		 * generate random hash
		 *
		 * @param int $length The desired hash string length default 32
		 *
		 * @return string
		 */
		public static function genRandomHash($length = 32)
		{
			return self::hashIt(OZoneStr::genRandomString(), $length);
		}

		/**
		 * generate session id
		 *
		 * @return string
		 */
		public static function genSid()
		{
			$salt = self::getSalt('OZ_SID_GEN_SALT');

			return self::hashIt(OZoneStr::genRandomString() . $salt, 32);
		}

		/**
		 * generate client id for a given client url
		 *
		 * @param string $url the client url
		 *
		 * @return string
		 */
		public static function genClid($url)
		{
			$salt = self::getSalt('OZ_CLID_GEN_SALT');
			$str  = self::hashIt($url . $salt, 32);

			return implode('-', str_split(strtoupper($str), 8));
		}

		/**
		 * generate a 6 digits authentication code
		 *
		 * @return int
		 */
		public static function genAuthCode()
		{
			srand(microtime() * 100);

			return rand(111111, 999999);
		}

		/**
		 * generate authentication token
		 *
		 * @param string|int $key the key to authenticate
		 *
		 * @return string
		 */
		public static function genAuthToken($key)
		{
			$salt = self::getSalt('OZ_AUTH_TOKEN_SALT');

			$str = $key . OZoneStr::genRandomString() . microtime() . $salt;

			return self::hashIt($str, 32);
		}
	}