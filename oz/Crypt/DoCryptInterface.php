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

	defined('OZ_SELF_SECURITY_CHECK') or die;

	interface DoCryptInterface
	{

		/**
		 * Gets password hash
		 *
		 * @param string $pass The password to be hashed
		 *
		 * @return string
		 */
		public function passHash($pass);

		/**
		 * Checks password
		 *
		 * @param string $pass       The password to be hashed
		 * @param string $known_hash The correct password hash
		 *
		 * @return bool
		 */
		public function passCheck($pass, $known_hash);

		// TODO
		// public function encrypt ( $pass_phrase, $input );
		// public function decrypt ( $pass_phrase, $input );
		// public function encryptFile ( $pass_phrase, $file_path, $destination_path );
		// public function decryptFile ( $pass_phrase, $file_path, $destination_path );
	}