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

	function ofv_sex( OFormValidator $ofv ) {
		$sex = $ofv->getField( 'sex' );

		if ( !preg_match( OZoneSettings::get( 'oz.ofv.const', 'OZ_SEX_REG' ), $sex ) ) {
			$ofv->addError( 'OZ_FIELD_SEX_INVALID' );
		} else {
			$ofv->setField( 'sex', $sex );
		}
	}