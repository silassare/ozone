<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneServiceLogout extends OZoneService {

		public function __construct() {
			parent::__construct();
		}

		public function execute( $request = array() ) {
			OZoneUserUtils::logOut();
			self::$resp->setDone( 'OZ_USER_LOGOUT' );
		}
	}