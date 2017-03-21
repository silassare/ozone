<?php

	namespace OZONE\OZ\Ofv;

	use OZONE\OZ\Core\OZoneSettings;
	use OZONE\OZ\Utils\OZoneStr;

	function ofv_email( OFormValidator $ofv ) {
		$email = $ofv->getField( 'email' );
		$email_reg = OZoneSettings::get('oz.user','OZ_EMAIL_REG');

		if ( !preg_match( $email_reg, $email ) ) {
			$ofv->addError( 'OZ_EMAIL_INVALID' );
		} else {
			$ofv->setField( 'email', OZoneStr::clean( $email ) );
		}
	}