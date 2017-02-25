<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	class OZoneErrorUnauthorizedAction extends OZoneError {
		public function __construct( $msg = 'OZ_ERROR_NOT_ALLOWED', $data = null ) {
			parent::__construct( OZoneError::UNAUTHORIZED_ACTION, __CLASS__, $msg, $data );
		}

		public function procedure() {
			if ( OZoneRequest::isPost() ) {
				$this->showJson();
			} else {
				//SILO::TODO force un retour a la page precedente
				//et affiche un message d'erreur
				//ou si grave ce qui suit
				$this->showCustomErrorPage();
			}
		}
	}