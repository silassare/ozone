<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Core;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	class OZoneServiceSample extends OZoneService {

		public function execute( $request = array() ) {
			self::$resp->setDone( "OZ_SAMPLE_SERVICE_HELLO" );
		}
	}