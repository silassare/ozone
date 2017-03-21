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

	use OZONE\OZ\Authenticator\Authenticator;
	use OZONE\OZ\Core\OZoneAssert;
	use OZONE\OZ\Core\OZoneService;
	use OZONE\OZ\Core\OZoneSessions;
	use OZONE\OZ\Core\OZoneSettings;
	use OZONE\OZ\Crypt\DoCrypt;
	use OZONE\OZ\Exceptions\OZoneForbiddenException;
	use OZONE\OZ\Ofv\OFormValidator;
	use OZONE\OZ\User\OZoneUserUtils;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	/**
	 * Class Signin
	 * @package OZONE\OZ\User\Services
	 */
	final class Signin extends OZoneService {
		const SIGNIN_STEP_START = 1;
		const SIGNIN_STEP_VALIDATE = 2;
		const SIGNIN_STEP_END = 3;

		/**
		 * OZoneServiceSignin constructor.
		 */
		public function __construct() {
			parent::__construct();
		}

		public function execute( $request = array() ) {
			$step = OZoneSessions::get( 'svc_signin:next_step' );

			if ( isset( $request[ 'step' ] ) ) {
				$step = intval( $request[ 'step' ] );
			}

			switch ( $step ) {
				case self::SIGNIN_STEP_START :
					$this->stepStart( $request );
					break;
				case self::SIGNIN_STEP_VALIDATE :
					$this->stepValidate( $request );
					break;
				case self::SIGNIN_STEP_END :
					$this->stepEnd( $request );
					break;
				default:
					//Etape d'inscription invalide
					self::$resp->setError( 'OZ_ERROR_INVALID_FORM' )->setKey( 'step', $step );
			}
		}

		/**
		 * start a new registration process
		 *
		 * @param array $request
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneInvalidFormException
		 */
		private function stepStart( array $request ) {
			//on deconnecte user
			OZoneUserUtils::logOut();

			//le formulaire est t-il au complet
			OZoneAssert::assertForm( $request, array( 'cc2', 'phone' ) );

			//verification du formulaire
			$fv_obj = new OFormValidator( $request );

			$fv_obj->checkForm( array(
				'cc2'   => null,
				'phone' => array( 'not-registered' )
			) );

			$form = $fv_obj->getForm();

			$cc2 = $form[ 'cc2' ];
			$phone = $form[ 'phone' ];

			$auth_obj = new Authenticator( 'svc_signin', $phone );

			//on verifie si ce user a deja entamer le processus d'inscription ou non
			if ( !$auth_obj->exists() ) {
				$this->sendAuthCodeResp( $auth_obj, $phone, 'OZ_AUTH_CODE_SENT' );
			} else {
				$this->sendAuthCodeResp( $auth_obj, $phone, 'OZ_AUTH_CODE_NEW_SENT' );
			}

			//si user est arriver jusque ici alors tout 'semble' aller bien: alors on peut se rappeller de lui et il vas a l'étape2
			OZoneSessions::set( 'svc_signin', array(
				'next_step'    => self::SIGNIN_STEP_VALIDATE,
				'cc2'          => $cc2,
				'phone'        => $phone,
				'num_verified' => false
			) );
		}

		/**
		 * validate the user login (phone or email)
		 *
		 * @param array $request
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneInvalidFormException
		 */
		private function stepValidate( array $request ) {
			$saved_form = OZoneSessions::get( 'svc_signin' );

			//assert if previous step is successful
			OZoneAssert::assertForm( $saved_form, array( 'phone', 'cc2' ), new OZoneForbiddenException( 'OZ_SIGNIN_STEP_1_INVALID' ) );

			//le formulaire est t-il au complet
			OZoneAssert::assertForm( $request, array( 'code' ) );

			//verification du formulaire
			$fv_obj = new OFormValidator( $request );

			$fv_obj->checkForm( array(
				'code' => null
			) );

			$code = $request[ 'code' ];

			//$cc2 = $saved_form[ 'cc2' ];
			$phone = $saved_form[ 'phone' ];

			$auth_obj = new Authenticator( 'svc_signin', $phone );

			if ( $auth_obj->validateCode( $code ) ) {
				//ok on peut passer a l'étape suivante
				OZoneSessions::set( 'svc_signin:next_step', self::SIGNIN_STEP_END );
				OZoneSessions::set( 'svc_signin:num_verified', true );

				self::$resp->setDone( $auth_obj->getMessage() );

			} else {
				self::$resp->setError( $auth_obj->getMessage() );
			}
		}

		/**
		 * end the registration process with all required user data
		 *
		 * @param array $request
		 *
		 * @throws \Exception
		 * @throws \OZONE\OZ\Exceptions\OZoneInternalError
		 * @throws \OZONE\OZ\Exceptions\OZoneInvalidFormException
		 * @throws \OZONE\OZ\Exceptions\OZoneUnauthorizedActionException
		 * @throws \OZONE\OZ\Exceptions\OZoneUnverifiedUserException
		 */
		private function stepEnd( array $request ) {
			$saved_form = OZoneSessions::get( 'svc_signin' );

			//assert if previous step is successful
			OZoneAssert::assertForm( $saved_form, array( 'cc2', 'phone', 'num_verified' ), new OZoneForbiddenException( 'OZ_SESSION_INVALID' ) );

			//on verifie que le numéro est deja verifie
			if ( !$saved_form[ 'num_verified' ] ) {
				OZoneSessions::remove( 'svc_signin' );
				OZoneAssert::assertAuthorizeAction( false, 'OZ_SIGNIN_STEP_2_INVALID' );
			}

			$cc2 = $saved_form[ 'cc2' ];
			$phone = $saved_form[ 'phone' ];

			//on verifie que le formulaire est au complet
			OZoneAssert::assertForm( $request, array( 'uname', 'pass', 'vpass', 'bdate', 'sex' ) );

			//verification du formulaire
			$fv_obj = new OFormValidator( $request );

			$fv_obj->checkForm( array(
				'uname' => null,
				'pass'  => null,
				'vpass' => null,
				'bdate' => null,
				'sex'   => null
			) );

			$form = $fv_obj->getForm();

			$uname = $form[ 'uname' ];
			$pass = $form[ 'pass' ];
			$bdate = $form[ 'bdate' ];
			$sex = $form[ 'sex' ];

			$crypt_obj = new DoCrypt();

			//on peut inscrire notre user
			$user_obj = OZoneUserUtils::getUserObject( 0 );

			$uid = $user_obj->createNewUser( array(
				'phone' => $phone,
				'email' => '',
				'pass'  => $crypt_obj->passHash( $pass ),
				'name'  => $uname,
				'sex'   => $sex,
				'bdate' => $bdate,
				'picid' => OZoneSettings::get( 'oz.user', 'OZ_DEFAULT_PICID' ),
				'cc2'   => $cc2
			) );

			//inscription terminer
			OZoneSessions::remove( 'svc_signin' );

			//on le connecte
			self::$resp->setDone( 'OZ_SIGNIN_SUCCESS' )->setData( OZoneUserUtils::logOn( $uid ) );
		}

		/**
		 * send the authentication code
		 *
		 * @param \OZONE\OZ\Authenticator\Authenticator $auth_obj
		 * @param string                                $phone
		 * @param string                                $msg
		 */
		private function sendAuthCodeResp( Authenticator $auth_obj, $phone, $msg ) {

			$captcha_src = $auth_obj->generate()->getCaptcha();

			self::$resp->setDone( $msg )->setData( array(
				'phone'   => $phone,
				'captcha' => $captcha_src
			) );
		}
	}