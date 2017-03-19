<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	//========================================== MUST BE SET : FOR OZONE USAGE
	define( 'OZ_APP_MAIN_URL', 'http://www.ozone.com' );

	define( 'OZ_APP_DEBUG_MODE', 0 );

	if ( OZ_APP_DEBUG_MODE === 1 ) {
		define( 'OZ_COOKIE_DOMAIN', $_SERVER[ 'SERVER_ADDR' ] );
	} else {
		define( 'OZ_COOKIE_DOMAIN', '.ozone.com' );
	}

	define( 'OZ_COOKIE_SID_NAME', 'OZONE_SID' );
	define( 'OZ_APIKEY_HEADER_NAME', 'x-ozone-apikey' );
	define( 'OZ_HTTP_APIKEY_HEADER_NAME', 'HTTP_X_OZONE_APIKEY' );

	//========================================== MUST BE SET : DATABASE INFOS

	define( 'OZ_DB_HOST', '' );
	define( 'OZ_DB_NAME', '' );
	define( 'OZ_DB_USER', '' );
	define( 'OZ_DB_PASS', '' );

	//========================================== YOUR OZONE APP CUSTOM CONSTANTS