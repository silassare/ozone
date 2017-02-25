<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	class OZoneErrorUnverifiedUser extends OZoneError {
		public function __construct( $msg = 'OZ_ERROR_YOU_MUST_LOGIN', $data = null ) {
			parent::__construct( OZoneError::UNVERIFIED_USER, __CLASS__, $msg, $data );
		}

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