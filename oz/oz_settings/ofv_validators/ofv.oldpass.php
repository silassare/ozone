<?php

	function ofv_oldpass( OFormValidator $ofv ) {
		$oldpass = $ofv->form[ 'oldpass' ];
		$phone = OZoneSessions::get( 'ozone_user:infos:phone' );

		if ( OZoneUserUtils::passOk( 'phone', $phone, $oldpass ) ) {
			$ofv->updateForm( 'oldpass', $oldpass );
		} else {
			//SILO::TODO
			//why not log off user and force user to login again?
			$ofv->addError( new OZoneErrorUnauthorizedAction( 'OZ_FIELD_OLDPASS_INVALID' ) );
		}
	}