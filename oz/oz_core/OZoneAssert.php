<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneAssert {

		public static function assertSafeRequestMethod( $required_methods, $msg = null, $data = null ) {
			$ok = false;

			foreach ( $required_methods as $method ) {

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

			if ( !( $msg instanceof OZoneError ) ) {
				$msg = new OZoneErrorBadRequest( $msg, $data );
			}

			throw $msg;
		}

		public static function assertUserVerified( $msg = 'OZ_ERROR_YOU_MUST_LOGIN', $data = null ) {
			if ( !OZoneUserUtils::userVerified() ) {
				if ( !( $msg instanceof OZoneError ) ) {
					$msg = new OZoneErrorUnverifiedUser( $msg, $data );
				}

				throw $msg;
			}
		}

		public static function assertIsAdmin( $msg = 'OZ_YOU_ARE_NOT_ADMIN', $data = null ) {
			if ( !OZoneUserUtils::userVerified() OR !OZoneAdminUtils::isAdmin( OZoneSessions::get( 'ozone_user:user_id' ) ) ) {
				if ( !( $msg instanceof OZoneError ) ) {
					$msg = new OZoneErrorUnverifiedUser( $msg, $data );
				}

				throw $msg;
			}
		}

		public static function assertAuthorizeAction( $result, $msg = 'OZ_ERROR_NOT_ALLOWED', $data = null ) {
			if ( !$result ) {
				if ( !( $msg instanceof OZoneError ) ) {
					$msg = new OZoneErrorUnauthorizedAction( $msg, $data );
				}

				throw $msg;
			}
		}

		public static function assertOperationSuccess( $result ) {
			if ( $result instanceof OZoneError )
				throw $result;
		}

		public static function assertForm( $form = null, $required_fields = null, $msg = 'OZ_ERROR_INVALID_FORM', $data = null ) {
			$safe = true;

			if ( empty( $form ) OR !is_array( $form ) ) {
				$safe = false;
			} elseif ( empty( $required_fields ) OR !is_array( $required_fields ) ) {
				$safe = false;
			} else {
				$safe = OFormUtils::isFormComplete( $form, $required_fields );
			}

			if ( !$safe ) {
				if ( !( $msg instanceof OZoneError ) ) {
					$msg = new OZoneErrorInvalidForm( $msg, $data );
				}

				throw $msg;
			}
		}
	}