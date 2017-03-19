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

	use OZONE\OZ\User\OZoneUserUtils;

	function ofv_phone( OFormValidator $ofv ) {

		$phone = $ofv->getField( 'phone' );
		$rules = $ofv->getRules( 'phone' );
		$rules = is_array( $rules ) ? $rules : array();

		//on verifie si le numéro est valide
		if ( !OZoneUserUtils::isPhoneNumberPossible( $phone ) ) {
			//numéro invalide
			$ofv->addError( 'OZ_FIELD_PHONE_INVALID' );

		} else if ( in_array( 'not-registered', $rules ) AND OZoneUserUtils::registered( 'phone', $phone ) ) {
			//numéro deja inscrit
			$ofv->addError( 'OZ_FIELD_PHONE_ALREADY_REGISTERED', array( 'phone' => $phone ) );

		} else if ( in_array( 'registered', $rules ) AND !OZoneUserUtils::registered( 'phone', $phone ) ) {
			//ce numéro n'est pas inscrit
			$ofv->addError( 'OZ_FIELD_PHONE_NOT_REGISTERED' );

		} else {
			$ofv->setField( 'phone', $phone );
		}

	}
