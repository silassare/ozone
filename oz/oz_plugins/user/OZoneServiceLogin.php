<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneServiceLogin extends OZoneService {
		public function __construct() {
			parent::__construct();
		}

		public function execute( $request = array() ) {
			OZoneAssert::assertForm( $request, array( 'phone', 'pass' ) );
			$phone = $request[ 'phone' ];
			$pass = $request[ 'pass' ];

			//et oui! user nous a soumit un formulaire alors on verifie que le formulaire est valide.
			if ( OZoneUserUtils::userVerified() AND $phone === OZoneSessions::get( 'ozone_user:infos:phone' ) AND OZoneUserUtils::passOk( 'phone', $phone, $pass ) ) {
				//on connecte user
				//et on lui renvois les infos sur lui
				self::$resp->setDone( 'OZ_USER_ONLINE' )->setData( OZoneUserUtils::tryLogOnWith( 'phone', $phone, $pass ) );

			} else {
				OZoneUserUtils::logOut();

				$fv_obj = OZone::obj( 'OFormValidator', $request );

				$fv_obj->checkForm( array(
					'phone' => array( 'registered' )
				) );

				$form = $fv_obj->getForm();
				$phone = $form[ 'phone' ];

				if ( OZoneUserUtils::passOk( 'phone', $phone, $pass ) ) {
					//on connecte user
					//et on lui renvois les infos sur lui
					self::$resp->setDone( 'OZ_USER_ONLINE' )->setData( OZoneUserUtils::tryLogOnWith( 'phone', $phone, $pass ) );

				} else {
					//le mot de passe n'est pas correct
					self::$resp->setError( 'OZ_FIELD_PASS_INVALID' );
				}
			}
		}
	}