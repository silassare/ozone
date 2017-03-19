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

	class OZoneBadRequestException extends OZoneBaseException {

		/**
		 * OZoneBadRequestException constructor.
		 *
		 * @param string     $message the exception message
		 * @param array|null $data    additional exception data
		 */
		public function __construct( $message = 'OZ_ERROR_BAD_REQUEST', array $data = null ) {
			parent::__construct( $message, OZoneBaseException::BAD_REQUEST, $data );
		}

		/**
		 * {@inheritdoc}
		 */
		public function procedure() {
			if ( OZoneRequest::isPost() ) {
				$this->showJson();
			} else {
				$this->showCustomErrorPage();
			}
		}
	}