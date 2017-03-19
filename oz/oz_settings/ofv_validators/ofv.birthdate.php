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

	function ofv_birthdate( OFormValidator $ofv ) {
		$bdate = $ofv->getField( 'bdate' );
		$safe = !empty( $bdate );
		//standard
		$DATE_REG_A = '#^(\d{4})[\-\/](\d{1,2})[\-\/](\d{1,2})$#';
		//when browser threat date field as text field (in firefox)
		$DATE_REG_B = '#^(\d{1,2})[\-\/](\d{1,2})[\-\/](\d{4})$#';

		$year = $month = $day = "";

		$in_a = array();
		$in_b = array();

		if ( $safe && preg_match( $DATE_REG_A, $bdate, $in_a ) ) {

			$year = intval( $in_a[ 1 ] );
			$month = intval( $in_a[ 2 ] );
			$day = intval( $in_a[ 3 ] );

		} elseif ( $safe && preg_match( $DATE_REG_B, $bdate, $in_b ) ) {

			$year = intval( $in_b[ 3 ] );
			$month = intval( $in_b[ 2 ] );
			$day = intval( $in_b[ 1 ] );

		} else {
			$safe = false;
		}

		//on verifie que la date de naissance est valide
		if ( $safe AND OFormUtils::isBirthDate( $month, $day, $year, OZoneSettings::get( 'oz.ofv.const', 'OZ_USER_MIN_AGE' ), OZoneSettings::get( 'oz.ofv.const', 'OZ_USER_MAX_AGE' ) ) ) {
			$ofv->setField( 'bdate', $day . '-' . $month . '-' . $year );
		} else {
			//la date de naissance n'est pas valide
			$ofv->addError( 'OZ_FIELD_BIRTHDATE_INVALID' );
		}
	}
