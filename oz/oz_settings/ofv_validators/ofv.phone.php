<?php

	function ofv_phone( OFormValidator $ofv ) {

		$phone = $ofv->getField( 'phone' );
		$rules = $ofv->getRules( 'phone' );
		$rules = is_array( $rules ) ? $rules : array();

		//on verifie si le numéro est valide
		if ( !OZoneUserUtils::isPhoneNumberPossible( $phone ) ) {
			//numéro invalide
			$ofv->addError( 'OZ_FIELD_PHONE_INVALID' );

		} else if ( in_array( 'not-registered', $rules ) AND OZoneUserUtils::registered( $phone ) ) {
			//numéro deja inscrit
			$ofv->addError( 'OZ_FIELD_PHONE_ALREADY_REGISTERED', array( 'phone' => $phone ) );

		} else if ( in_array( 'registered', $rules ) AND !OZoneUserUtils::registered( $phone ) ) {
			//ce numéro n'est pas inscrit
			$ofv->addError( 'OZ_FIELD_PHONE_NOT_REGISTERED' );

		} else {
			$ofv->updateForm( 'phone', $phone );
		}

	}
