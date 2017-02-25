<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	interface OZoneAppBase {
		public static function onInit();

		public static function onError( OZoneError $err );
	}