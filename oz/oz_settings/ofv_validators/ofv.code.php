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
	use OZONE\OZ\Utils\OZoneStr;

	function ofv_code( OFormValidator $ofv ) {
		$code = $ofv->getField( 'code' );

		if ( !preg_match( OZoneSettings::get( 'oz.ofv.const', 'OZ_CODE_REG' ), $code ) ) {
			$ofv->setField( 'code', OZoneStr::clean( $code ) );
		} else {
			$ofv->addError( 'OZ_AUTH_CODE_INVALID' );
		}
	}