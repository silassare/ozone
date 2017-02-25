<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	class OZoneServiceTestNet extends OZoneService {

		public function __construct() {
			parent::__construct();
		}

		public function execute( $request = array() ) {
			$data = array();
			$uid = OZoneSessions::get( 'ozone_user:user_id' );
			//on verifie si user a une session en cours
			if ( OZoneUserUtils::userVerified() AND !empty( $uid ) ) {
				$data[ 'ok' ] = 1;
				//SILO:: embigue mais c'est juste pour pouvoir retourner les infos de users
				$data[ 'infos' ] = OZoneUserUtils::logOn( $uid );

			} elseif ( $this->canWeRememberUser( $request ) ) {
				$data[ 'ok' ] = 1;
				$data[ 'infos' ] = OZoneUserUtils::logOn( $request[ 'uid' ] );
			} else {
				$data[ 'ok' ] = 0;
				$step = OZoneSessions::get( 'svc_signin:step' );
				$phone = OZoneSessions::get( 'svc_signin:phone' );
				$captcha_img = OZoneSessions::get( 'svc_signin:captcha:image_src' );

				//on verifie si user est en cours d'inscription
				if ( !empty( $step ) AND !empty( $phone ) AND !empty( $captcha_img ) ) {
					$data[ 'ins' ] = array( $step, $phone, $captcha_img );
				}
			}

			//SILO::TODO on verifie si il y a une mise a jours en executant le service des mises a jours
			self::$resp->setData( $data );
		}

		private function canWeRememberUser( $request ) {

			if ( isset( $request[ 'uid' ] ) AND isset( $request[ 'token' ] ) ) {
				$uid = $request[ 'uid' ];
				$token = $request[ 'token' ];
				//SILO::TODO high security risk here
				//so be carefull
				return OZoneRequest::getClient()->hasUser( $uid, $token );
			}

			return false;
		}
	}