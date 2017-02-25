<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneApp implements OZoneAppBase {
		public static function onInit() {
		}

		public static function onError( OZoneError $err ) {

			return false;
		}
	}