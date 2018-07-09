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

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class Hasher
	{
		const CHARS_ALPHA     = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		const CHARS_NUM       = '0123456789';
		const CHARS_SYMBOLS   = '~!@#$£µ§²¨%^&()_-+={}[]:";\'<>?,./\\';
		const CHARS_ALPHA_NUM = Hasher::CHARS_ALPHA . Hasher::CHARS_NUM;
		const CHARS_ALL       = Hasher::CHARS_ALPHA_NUM . Hasher::CHARS_SYMBOLS;

		/**
		 * @param string $name The 'oz.keygen.salt' settings salt key name.
		 *
		 * @return mixed|null
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		private static function getSalt($name)
		{
			return SettingsManager::get('oz.keygen.salt', $name);
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
			// if not, all clone will have the same file_key as the original file
			srand(self::genSeed());

			$file_salt = self::genRandomString() . microtime() . self::getSalt('OZ_FILE_KEY_GEN_SALT');
			$str       = md5_file($path) . $file_salt;

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

				throw new \InvalidArgumentException("hash length argument should be on of this list: $values");
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
			return self::hashIt(self::genRandomString() . microtime(), $length);
		}

		/**
		 * generate a seed
		 *
		 * Test result:
		 *  - Total seeds  -----> 1000000
		 *  - Unique seeds -----> 999805
		 *  - Redundancies -----> 195
		 *  - Duration     -----> 1.3364629745483 s
		 *
		 * @return int
		 */
		public static function genSeed()
		{
			$m = explode(' ', microtime());
			$m = ($m[0] * $m[1]) . '.';
			$m = explode('.', $m);

			return $m[0] + $m[1];
		}

		/**
		 * generate a random string for a given length
		 *
		 * @param int    $length The desired random string length default 32 range is [1,512]
		 * @param string $chars  The chars to use
		 *
		 * @return string
		 */
		public static function genRandomString($length = 32, $chars = Hasher::CHARS_ALL)
		{
			$min = 1;
			$max = 512;

			if ($length < $min OR $length > $max)
				throw new \InvalidArgumentException(sprintf('Random string length must be between %d and %d.', $min, $max));

			if (strlen($chars) < 2)
				throw new \InvalidArgumentException('Require at least 2 chars to generate random string.');

			$chars_length = strlen($chars) - 1;
			$string       = '';
			srand(self::genSeed());

			for ($i = 0; $i < $length; ++$i) {
				$string .= $chars[rand(0, $chars_length)];
			}

			return $string;
		}

		/**
		 * generate session id
		 *
		 * @return string
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public static function genSessionId()
		{
			$salt = self::getSalt('OZ_SESSION_ID_GEN_SALT');

			return self::hashIt(self::genRandomString() . microtime() . $salt, 32);
		}

		/**
		 * generate client id for a given client url
		 *
		 * @param string $url the client url
		 *
		 * @return string
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public static function genClientId($url)
		{
			$salt = self::getSalt('OZ_CLIENT_ID_GEN_SALT');
			$str  = self::hashIt($url . microtime() . $salt, 32);

			return implode('-', str_split(strtoupper($str), 8));
		}

		/**
		 * Generate auth code
		 *
		 * @param int  $length    the auth code length
		 * @param bool $alpha_num whether to use digits or alpha_num
		 *
		 * @return string
		 */
		public static function genAuthCode($length = 4, $alpha_num = false)
		{
			$min = 4;
			$max = 32;

			if ($length < $min OR $length > $max)
				throw new \InvalidArgumentException(sprintf('Auth code length must be between %d and %d.', $min, $max));

			if ($alpha_num) {
				return self::genRandomString($length, Hasher::CHARS_ALPHA_NUM);
			}

			srand(self::genSeed());

			$code = rand(111111, 999999);
			$code .= rand(111111, 999999);
			$code .= rand(111111, 999999);
			$code .= rand(111111, 999999);
			$code .= rand(111111, 999999);
			$code .= rand(111111, 999999);

			return substr($code, 0, $length);
		}

		/**
		 * Generate auth token
		 *
		 * @param string|int $key the key to authenticate
		 *
		 * @return string
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public static function genAuthToken($key)
		{
			$salt = self::getSalt('OZ_AUTH_TOKEN_SALT');

			$str = $key . self::genRandomString() . microtime() . $salt;

			return self::hashIt($str, 32);
		}
	}