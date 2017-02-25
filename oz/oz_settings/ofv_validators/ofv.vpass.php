<?php

	function ofv_vpass( OFormValidator $ofv ) {
		//on verifie que pass et vpass sont identiques
		if ( !OFormUtils::equalFields( $ofv, 'pass', 'vpass' ) ) {
			//pass et vpass ne sont pas identiques
			$ofv->addError( 'OZ_FIELD_PASS_AND_VPASS_NOT_EQUAL' );
		}
	}