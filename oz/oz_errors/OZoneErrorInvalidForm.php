<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	class OZoneErrorInvalidForm extends OZoneError {
		public function __construct( $msg = 'OZ_ERROR_INVALID_FORM', $data = null ) {
			parent::__construct( OZoneError::INVALID_FORM, __CLASS__, $msg, $data );
		}

		public function procedure() {
			if ( OZoneRequest::isPost() ) {
				$this->showJson();
			} else {
				//SILO::TODO reaffiche le formullaire si il s'agit d'un formullaire
				//si non retour sur la dernier page
				//ou si grave ce qui suit
				$this->showCustomErrorPage();
			}
		}
	}