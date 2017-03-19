<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Exceptions;

	use OZONE\OZ\Core\OZoneRequest;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	class OZoneUnverifiedUserException extends OZoneBaseException {

		/**
		 * OZoneUnverifiedUserException constructor.
		 *
		 * @param string     $message the exception message
		 * @param array|null $data    additional exception data
		 */
		public function __construct( $message = 'OZ_ERROR_YOU_MUST_LOGIN', array $data = null ) {
			parent::__construct( $message, OZoneBaseException::UNVERIFIED_USER, $data );
		}

		/**
		 * {@inheritdoc}
		 */
		public function procedure() {
			if ( OZoneRequest::isPost() ) {
				//SILO::TODO force user a se connecter
				$this->showJson();
			} else {
				//SILO::TODO redirect to login page
				//et affiche un message d'erreur
				//ou si grave ce qui suit
				$this->showCustomErrorPage();
			}
		}
	}