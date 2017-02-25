<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	class OZoneErrorNotFound extends OZoneError {
		public function __construct( $msg = 'OZ_ERROR_NOT_FOUND', $data = null ) {
			parent::__construct( OZoneError::NOT_FOUND, __CLASS__, $msg, $data );
		}

		public function procedure() {
			if ( OZoneRequest::isPost() ) {
				$this->showJson();
			} else {
				$this->showCustomErrorPage();
			}
		}
	}