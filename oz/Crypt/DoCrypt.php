<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Crypt;

	use OZONE\OZ\Core\Hasher;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	class DoCrypt implements CryptInterface
	{

		/**
		 * BCRYPT algorithm max input length is 72
		 *
		 * @var int
		 */
		const BCRYPT_MAX_INPUT_LENGTH = 72;

		/**
		 * DoCrypt constructor.
		 */
		public function __construct()
		{
		}

		/**
		 *{@inheritdoc}
		 */
		public function isHash($pass)
		{
			$pass_info = password_get_info($pass);

			return $pass_info['algo'] === PASSWORD_BCRYPT;
		}

		/**
		 *{@inheritdoc}
		 */
		public function passHash($pass)
		{
			return password_hash(self::toShort($pass), PASSWORD_BCRYPT);
		}

		/**
		 * {@inheritdoc}
		 */
		public function passCheck($pass, $known_hash)
		{
			return password_verify(self::toShort($pass), $known_hash);
		}

		/**
		 * shorten password to comply with BCRYPT algorithm max input length (72)
		 *
		 * @param string $pass the password
		 *
		 * @return string
		 */
		private static function toShort($pass)
		{
			if (strlen($pass) > self::BCRYPT_MAX_INPUT_LENGTH) {
				$pass = Hasher::hashIt($pass, 64);
			}

			return $pass;
		}
	}