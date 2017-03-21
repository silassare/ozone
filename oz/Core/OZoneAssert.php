<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Core;

	use OZONE\OZ\Admin\AdminUtils;
	use OZONE\OZ\Exceptions\OZoneBadRequestException;
	use OZONE\OZ\Exceptions\OZoneBaseException;
	use OZONE\OZ\Exceptions\OZoneInvalidFormException;
	use OZONE\OZ\Exceptions\OZoneUnauthorizedActionException;
	use OZONE\OZ\Exceptions\OZoneUnverifiedUserException;
	use OZONE\OZ\Ofv\OFormUtils;
	use OZONE\OZ\User\OZoneUserUtils;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneAssert {

		/**
		 * assert if the request method is authorized
		 *
		 * @param array                          $required_methods the required methods
		 * @param OZoneBaseException|string|null $msg              the error message
		 * @param mixed                          $data             the error data
		 *
		 * @return bool
		 * @throws \OZONE\OZ\Exceptions\OZoneBaseException
		 * @throws \OZONE\OZ\Exceptions\OZoneBadRequestException
		 * @throws string
		 */
		public static function assertSafeRequestMethod( $required_methods, $msg = 'OZ_ERROR_BAD_REQUEST_METHOD', $data = null ) {
			$ok = false;

			foreach ( $required_methods as $method ) {
				$method = strtoupper( $method );

				switch ( $method ) {
					case 'POST' :
						$ok = OZoneRequest::isPost();
						break;
					case 'GET' :
						$ok = OZoneRequest::isGet();
						break;
					case 'PUT' :
						$ok = OZoneRequest::isPut();
						break;
					case 'OPTIONS' :
						$ok = OZoneRequest::isOptions();
						break;
					case 'DELETE' :
						$ok = OZoneRequest::isDelete();
						break;
				}

				if ( $ok === true )
					break;
			}

			if ( $ok === true )
				return $ok;

			if ( !( $msg instanceof OZoneBaseException ) ) {
				$msg = new OZoneBadRequestException( $msg, $data );
			}

			throw $msg;
		}

		/**
		 * assert if the current user is verified
		 *
		 * @param OZoneBaseException|string|null $msg  the error message
		 * @param mixed                          $data the error data
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneBaseException
		 * @throws \OZONE\OZ\Exceptions\OZoneUnverifiedUserException
		 * @throws string
		 */
		public static function assertUserVerified( $msg = 'OZ_ERROR_YOU_MUST_LOGIN', $data = null ) {
			if ( !OZoneUserUtils::userVerified() ) {
				if ( !( $msg instanceof OZoneBaseException ) ) {
					$msg = new OZoneUnverifiedUserException( $msg, $data );
				}

				throw $msg;
			}
		}

		/**
		 * assert if the current user is a verified admin
		 *
		 * @param OZoneBaseException|string|null $msg  the error message
		 * @param mixed                          $data the error data
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneBaseException
		 * @throws \OZONE\OZ\Exceptions\OZoneUnverifiedUserException
		 * @throws string
		 */
		public static function assertIsAdmin( $msg = 'OZ_YOU_ARE_NOT_ADMIN', $data = null ) {
			if ( !OZoneUserUtils::userVerified() OR !AdminUtils::isAdmin( OZoneSessions::get( 'ozone_user:user_id' ) ) ) {
				if ( !( $msg instanceof OZoneBaseException ) ) {
					$msg = new OZoneUnverifiedUserException( $msg, $data );
				}

				throw $msg;
			}
		}

		/**
		 * assert if the result of a given expression is evaluated to true
		 *
		 * @param mixed                          $expression the expression
		 * @param OZoneBaseException|string|null $msg        the error message
		 * @param mixed                          $data       the error data
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneBaseException
		 * @throws \OZONE\OZ\Exceptions\OZoneUnauthorizedActionException
		 * @throws string
		 */
		public static function assertAuthorizeAction( $expression, $msg = 'OZ_ERROR_NOT_ALLOWED', $data = null ) {
			if ( !$expression ) {
				if ( $msg instanceof OZoneBaseException ) {
					$msg = new OZoneUnauthorizedActionException( $msg, $data );
				}

				throw $msg;
			}
		}

		/**
		 * assert if a given result is an ozone error
		 *
		 * @param mixed $result the result
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneBaseException
		 */
		public static function assertOperationSuccess( $result ) {
			if ( $result instanceof OZoneBaseException )
				throw $result;
		}

		/**
		 * assert if a given form contains has all required fields
		 *
		 * @param mixed  $form            the form to be checked
		 * @param array  $required_fields the required fields
		 * @param string $msg             the error message
		 * @param mixed  $data            the error data
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneBaseException
		 * @throws \OZONE\OZ\Exceptions\OZoneInvalidFormException
		 * @throws string
		 */
		public static function assertForm( $form = null, array $required_fields, $msg = 'OZ_ERROR_INVALID_FORM', $data = null ) {

			if ( empty( $form ) OR !is_array( $form ) ) {
				$safe = false;
			} else {
				$safe = OFormUtils::isFormComplete( $form, $required_fields );
			}

			if ( !$safe ) {
				if ( !( $msg instanceof OZoneBaseException ) ) {
					$msg = new OZoneInvalidFormException( $msg, $data );
				}

				throw $msg;
			}
		}
	}