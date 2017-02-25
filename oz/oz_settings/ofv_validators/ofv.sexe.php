<?php

	function ofv_sexe( OFormValidator $ofv ) {
		$sexe = $ofv->getField( 'sexe' );

		//on verifie que le sexe est valide
		if ( !preg_match( OZoneSettings::get( 'oz.ofv.const', 'OZ_SEXE_REG' ), $sexe ) ) {
			//le sexe n'est pas valide
			$ofv->addError( 'OZ_FIELD_SEXE_INVALID' );
		} else {
			$ofv->updateForm( 'sexe', $sexe );
		}
	}