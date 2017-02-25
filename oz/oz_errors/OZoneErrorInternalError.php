<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	class OZoneErrorInternalError extends OZoneError {
		public function __construct( $msg = 'OZ_ERROR_INTERNAL', $data = null ) {
			parent::__construct( OZoneError::INTERNAL_ERROR, __CLASS__, $msg, $data );
		}

		public function procedure() {
			if ( OZoneRequest::isPost() ) {
				$this->showJson();
			} else {
				$this->showCustomErrorPage();
			}
		}
	}