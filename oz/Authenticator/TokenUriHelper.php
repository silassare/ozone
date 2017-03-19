<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Authenticator;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	/**
	 * Class TokenUriHelper
	 * @package OZONE\OZ\Authenticator
	 */
	final class TokenUriHelper {

		public function __construct() {
		}

		/**
		 * get authenticator token uri for a given authenticator
		 *
		 * @param \OZONE\OZ\Authenticator\Authenticator $auth the authenticator to use
		 *
		 * @return string
		 */
		public static function getUri( Authenticator $auth ) {
			$generated = $auth->getGenerated();
			$label = $auth->getLabel();
			$token = $generated[ 'token' ];
			$uri = "$label/$token.auth";

			return $uri;
		}

		public function validate( $label, $token ) {

		}
	}