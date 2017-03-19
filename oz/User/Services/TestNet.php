<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\User\Services;

	use OZONE\OZ\Core\OZoneRequest;
	use OZONE\OZ\Core\OZoneService;
	use OZONE\OZ\Core\OZoneSessions;
	use OZONE\OZ\User\OZoneUserUtils;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	/**
	 * Class TestNet
	 * @package OZONE\OZ\User\Services
	 */
	class TestNet extends OZoneService {

		/**
		 * TestNet constructor.
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * {@inheritdoc}
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneUnverifiedUserException
		 */
		public function execute( $request = array() ) {
			$data = array();
			$uid = OZoneSessions::get( 'ozone_user:user_id' );
			//on verifie si user a une session en cours
			if ( OZoneUserUtils::userVerified() AND !empty( $uid ) ) {
				$data[ 'ok' ] = 1;
				//SILO:: embigue mais c'est juste pour pouvoir retourner les infos de users
				$data[ '_current_user' ] = OZoneUserUtils::logOn( $uid );

			} elseif ( $this->canWeRememberUser( $request ) ) {
				$data[ 'ok' ] = 1;
				$data[ '_current_user' ] = OZoneUserUtils::logOn( $request[ 'uid' ] );
			} else {
				$data[ 'ok' ] = 0;
				$step = OZoneSessions::get( 'svc_signin:step' );
				$phone = OZoneSessions::get( 'svc_signin:phone' );

				//on verifie si user est en cours d'inscription et qu'il est a l'etape de validation du code
				if ( !empty( $step ) AND !empty( $phone ) AND $step === Signin::SIGNIN_STEP_VALIDATE ) {
					$data[ '_info_signin' ] = array( 'step' => $step, 'phone' => $phone );
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
				return OZoneRequest::getCurrentClient()->hasUser( $uid, $token );
			}

			return false;
		}
	}