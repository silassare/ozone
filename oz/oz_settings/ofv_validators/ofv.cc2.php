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

	function ofv_cc2( OFormValidator $ofv ) {
		//code cc2 du pays concerné
		$cc2 = strtoupper( $ofv->getField( 'cc2' ) ); //<- important

		//le pays est t-il dans la liste des pays autorise
		if ( !OZoneUserUtils::authorizedCountry( $cc2 ) ) {
			//pays non autorise
			$ofv->addError( 'OZ_FIELD_COUNTRY_NOT_ALLOWED' );
		} else {
			$ofv->setField( 'cc2', $cc2 );
		}

	}