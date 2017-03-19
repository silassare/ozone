<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Ofv;

	use OZONE\OZ\Core\OZoneSessions;
	use OZONE\OZ\Exceptions\OZoneUnauthorizedActionException;
	use OZONE\OZ\User\OZoneUserUtils;

	function ofv_oldpass( OFormValidator $ofv ) {
		$oldpass = $ofv->getField( 'oldpass' );
		$phone = OZoneSessions::get( 'ozone_user:data:phone' );

		if ( OFormUtils::equalFields( $ofv, 'pass', 'oldpass' ) ) {
			$ofv->addError( 'OZ_FIELD_OLDPASS_AND_NEW_PASS_ARE_EQUAL' );
		} else if ( !OZoneUserUtils::passOk( 'phone', $phone, $oldpass ) ) {
			//SILO::TODO
			//why not log off user and force user to login again?
			//just uncomment the line bellow
			//OZoneUserUtils::logOut();
			$ofv->addError( new OZoneUnauthorizedActionException( 'OZ_FIELD_OLDPASS_INVALID' ) );
		} else {
			$ofv->setField( 'oldpass', $oldpass );
		}
	}