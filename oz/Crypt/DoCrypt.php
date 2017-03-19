<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Crypt;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	class DoCrypt implements DoCryptInterface {

		/**
		 * BCRYPT algorithm max input length is 72
		 * @var int
		 */
		const BCRYPT_MAX_INPUT_LENGTH = 72;

		/**
		 * DoCrypt constructor.
		 */
		public function __construct() {
		}

		/**
		 *{@inheritdoc}
		 */
		public function passHash( $pass ) {
			if ( strlen( $pass ) > self::BCRYPT_MAX_INPUT_LENGTH ) {
				throw new \InvalidArgumentException( 'your password length should not exceed ' . self::BCRYPT_MAX_INPUT_LENGTH );
			}

			return password_hash( $pass, PASSWORD_BCRYPT );
		}

		/**
		 * {@inheritdoc}
		 */
		public function passCheck( $pass, $known_hash ) {
			return password_verify( $pass, $known_hash );
		}
	}