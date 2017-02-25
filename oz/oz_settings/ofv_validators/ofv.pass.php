<?php

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
			$ofv->updateForm( 'pass', $pass );
		}
	}