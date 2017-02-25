<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	abstract class OZoneService {
		protected static $resp;

		public function __construct() {
			self::$resp = OZoneResponsesHolder::getInstance( $this->getServiceName() );
		}

		abstract public function execute( $request = array() );

		public function getServiceName() {
			return get_class( $this );;
		}

		public function getResp() {
			return self::$resp;
		}
	}