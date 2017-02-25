<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneServiceSignin extends OZoneService {
		const STEP_START = 1;
		const STEP_VALIDATE = 2;
		const STEP_SIGNIN = 3;

		public function __construct() {
			parent::__construct();
		}

		public function execute( $request = array() ) {
			$step = OZoneSessions::get( 'svc_signin:next_step' );

			if ( isset( $request[ 'step' ] ) ) {
				$step = intval( $request[ 'step' ] );
			}

			switch ( $step ) {
				case OZoneServiceSignin::STEP_START :
					$this->stepStart( $request );
					break;
				case OZoneServiceSignin::STEP_VALIDATE :
					$this->stepValidate( $request );
					break;
				case OZoneServiceSignin::STEP_SIGNIN :
					$this->stepSignin( $request );
					break;
				default:
					//Etape d'inscription invalide
					self::$resp->setError( 'OZ_ERROR_INVALID_FORM' )->setKey( 'step', $step );
			}
		}

		private function stepStart( $request ) {
			//on deconnecte user
			OZoneUserUtils::logOut();

			//le formulaire est t-il au complet
			OZoneAssert::assertForm( $request, array( 'cc2', 'phone' ) );

			//verification du formulaire
			$fv_obj = OZone::obj( 'OFormValidator', $request );

			$fv_obj->checkForm( array(
				'cc2'   => null,
				'phone' => array( 'not-registered' )
			) );

			$form = $fv_obj->getForm();

			$cc2 = $form[ 'cc2' ];
			$phone = $form[ 'phone' ];

			$auth_obj = new OZoneAuthenticator( 'svc_signin', $phone );

			//on verifie si ce user a deja entamer le processus d'inscription ou non
			if ( !$auth_obj->exists() ) {
				$this->setAuthCodeResp( $auth_obj, $phone, 'OZ_AUTH_CODE_SENT' );
			} else {
				$this->setAuthCodeResp( $auth_obj, $phone, 'OZ_AUTH_CODE_NEW_SENT' );
			}

			//si user est arriver jusque ici alors tout 'semble' aller bien: alors on peut se rappeller de lui et il vas a l'étape2
			OZoneSessions::set( 'svc_signin', array(
				'next_step'    => OZoneServiceSignin::STEP_VALIDATE,
				'cc2'          => $cc2,
				'phone'        => $phone,
				'num_verified' => false
			) );
		}

		private function stepValidate( $request ) {
			//on verifie si l'étape1 s'est bien deroulee
			$saved_form = OZoneSessions::get( 'svc_signin' );
			OZoneAssert::assertForm( $saved_form, array( 'phone', 'cc2' ), 'OZ_SIGNIN_STEP_1_INVALID' );

			//le formulaire est t-il au complet
			OZoneAssert::assertForm( $request, array( 'code' ) );

			$code = $request[ 'code' ];

			$cc2 = $saved_form[ 'cc2' ];
			$phone = $saved_form[ 'phone' ];

			$auth_obj = new OZoneAuthenticator( 'svc_signin', $phone );
			$result = $auth_obj->validateCode( $code );

			if ( $result[ 'ok' ] ) {
				//ok on peut passer a l'étape suivante
				OZoneSessions::set( 'svc_signin:next_step', OZoneServiceSignin::STEP_SIGNIN );
				OZoneSessions::set( 'svc_signin:num_verified', true );
				self::$resp->setDone( $result[ 'msg' ] );
			} else {
				self::$resp->setError( $result[ 'msg' ] );
			}
		}

		private function stepSignin( $request ) {
			//on verifie que les variables de sessions existent
			$saved_form = OZoneSessions::get( 'svc_signin' );
			OZoneAssert::assertForm( $saved_form, array( 'cc2', 'phone', 'num_verified' ), 'OZ_SESSION_INVALID' );

			//on verifie que le numéro est deja verifie
			if ( !$saved_form[ 'num_verified' ] ) {
				OZoneSessions::remove( 'svc_signin' );
				OZoneAssert::assertAuthorizeAction( false, 'OZ_SIGNIN_STEP_2_INVALID' );
			}

			$cc2 = $saved_form[ 'cc2' ];
			$phone = $saved_form[ 'phone' ];

			//on verifie que le formulaire est au complet
			OZoneAssert::assertForm( $request, array( 'uname', 'pass', 'vpass', 'bdate', 'sexe' ) );

			//verification du formulaire
			$fv_obj = OZone::obj( 'OFormValidator', $request );

			$fv_obj->checkForm( array(
				'uname' => null,
				'pass'  => null,
				'vpass' => null,
				'bdate' => null,
				'sexe'  => null
			) );

			$form = $fv_obj->getForm();

			$uname = $form[ 'uname' ];
			$pass = $form[ 'pass' ];
			$bdate = $form[ 'bdate' ];
			$sexe = $form[ 'sexe' ];

			//on peut inscrire notre user
			$user_obj = OZoneUserUtils::getUserObject( 0 );

			$uid = $user_obj->createNewUser( array(
				'phone' => $phone,
				'email' => '',
				'pass'  => OZoneKeyGen::passHash( $pass ),
				'name'  => $uname,
				'sexe'  => $sexe,
				'bdate' => $bdate,
				'picid' => OZoneSettings::get( 'oz.user', 'OZ_DEFAULT_PICID' ),
				'cc2'   => $cc2
			) );

			//inscription terminer
			OZoneSessions::remove( 'svc_signin' );

			//on le connecte
			self::$resp->setDone( 'OZ_SIGNIN_SUCCESS' )->setData( OZoneUserUtils::logOn( $uid ) );
		}

		private function setAuthCodeResp( OZoneAuthenticator $auth_obj, $phone, $msg ) {
			$result = $auth_obj->genCode();

			$c_src = OZoneServiceCaptchaCode::gen( array( 'code' => $result[ 'code' ] ) );

			OZoneSessions::set( 'svc_signin:captcha:image_src', $c_src );

			self::$resp->setDone( $msg )->setData( array(
				'phone'   => $phone,
				'captcha' => $c_src
			) );
		}
	}
