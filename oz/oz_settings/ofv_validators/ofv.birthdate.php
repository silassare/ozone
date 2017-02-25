<?php

	function ofv_birthdate( OFormValidator $ofv ) {
		$bdate = $ofv->getField( 'bdate' );
		$safe = !empty( $bdate );
		//standard
		$DATE_REG_A = '#^(\d{4})[\-\/]{1}(\d{1,2})[\-\/]{1}(\d{1,2})$#';
		//when browser threat date field as text field (in firefox)
		$DATE_REG_B = '#^(\d{1,2})[\-\/]{1}(\d{1,2})[\-\/]{1}(\d{4})$#';

		$in_a = array();
		$in_b = array();

		if ( $safe && preg_match( $DATE_REG_A, $bdate, $in_a ) ) {

			$annee = intval( $in_a[ 1 ] );
			$mois = intval( $in_a[ 2 ] );
			$jour = intval( $in_a[ 3 ] );

		} elseif ( $safe && preg_match( $DATE_REG_B, $bdate, $in_b ) ) {

			$annee = intval( $in_b[ 3 ] );
			$mois = intval( $in_b[ 2 ] );
			$jour = intval( $in_b[ 1 ] );

		} else {
			$safe = false;
		}

		//on verifie que la date de naissance est valide
		if ( $safe AND OFormUtils::isBirthdate( $mois, $jour, $annee, OZoneSettings::get( 'oz.ofv.const', 'OZ_USER_MIN_AGE' ), OZoneSettings::get( 'oz.ofv.const', 'OZ_USER_MAX_AGE' ) ) ) {
			$ofv->updateForm( 'bdate', $jour . '-' . $mois . '-' . $annee );
		} else {
			//la date de naissance n'est pas valide
			$ofv->addError( 'OZ_FIELD_BIRTHDATE_INVALID' );
		}
	}
