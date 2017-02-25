<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	class OZoneErrorBadRequest extends OZoneError {
		public function __construct( $msg = 'OZ_ERROR_BAD_REQUEST', $data = null ) {
			parent::__construct( OZoneError::BAD_REQUEST, __CLASS__, $msg, $data );
		}

		public function procedure() {
			if ( OZoneRequest::isPost() ) {
				$this->showJson();
			} else {
				$this->showCustomErrorPage();
			}
		}
	}