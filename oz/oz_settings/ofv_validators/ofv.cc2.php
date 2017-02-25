<?php

	function ofv_cc2( OFormValidator $ofv ) {
		//code cc2 du pays concerné
		$cc2 = strtoupper( $ofv->getField( 'cc2' ) ); //<- important

		//le pays est t-il dans la liste des pays autorise
		if ( !OZoneUserUtils::authorizedCountry( $cc2 ) ) {
			//pays non autorise
			$ofv->addError( 'OZ_FIELD_COUNTRY_NOT_ALLOWED' );
		} else {
			$ofv->updateForm( 'cc2', $cc2 );
		}

	}