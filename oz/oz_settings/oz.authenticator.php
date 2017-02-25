<?php

	OZoneSettings::set( 'oz.authenticator', array(
		//nombre maximum de tentative sur un code d'authentification reçu par sms (ou mail? lol)
		"OZ_AUTH_CODE_FAIL_MAX"  => 3,
		//temps maximum de validité du code d'authentification (en secondes)
		"OZ_AUTH_CODE_LIFE_TIME" => 60 * 60
	) );