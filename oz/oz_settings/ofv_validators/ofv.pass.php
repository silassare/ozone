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

	use OZONE\OZ\Core\OZoneSettings;

	function ofv_pass( OFormValidator $ofv ) {
		$pass = $ofv->getField( 'pass' );

		//on verifie la longueur du mot de pass
		$len = strlen( $pass );
		if ( $len < OZoneSettings::get( 'oz.ofv.const', 'OZ_PASS_MIN_LENGTH' ) ) {
			//mot de pass trop court
			$ofv->addError( 'OZ_FIELD_PASS_TOO_SMALL' );

		} else if ( $len > OZoneSettings::get( 'oz.ofv.const', 'OZ_PASS_MAX_LENGTH' ) ) {

			//mot de pass trop long
			$ofv->addError( 'OZ_FIELD_PASS_TOO_LONG' );

		} else {
			$ofv->setField( 'pass', $pass );
		}
	}