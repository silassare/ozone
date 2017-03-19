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

	use OZONE\OZ\Core\OZoneSettings;
	use OZONE\OZ\Utils\OZoneStr;

	function ofv_uname( OFormValidator $ofv ) {
		$uname = preg_replace( OZoneSettings::get( 'oz.ofv.const', 'OZ_UNWANTED_CHAR_REG' ), ' ', $ofv->getField( 'uname' ) );
		$uname = trim( $uname );

		$contains_key_words = preg_match( OZoneSettings::get( 'oz.ofv.const', 'OZ_EXCLUDE_KEY_WORDS' ), $uname );

		//on verifie le uname
		if ( !$contains_key_words AND preg_match( OZoneSettings::get( 'oz.ofv.const', 'OZ_UNAME_REG' ), $uname ) ) {
			$ofv->setField( 'uname', OZoneStr::clean( $uname ) );

		} else {
			//le uname n'est pas valide
			$e_msg = 'OZ_FIELD_USER_NAME_INVALID';

			if ( $contains_key_words ) {
				$e_msg = 'OZ_FIELD_USER_NAME_CONTAINS_KEYWORDS';

			} elseif ( strlen( $uname ) < OZoneSettings::get( 'oz.ofv.const', 'OZ_UNAME_MIN_LENGTH' ) ) {

				$e_msg = 'OZ_FIELD_USER_NAME_TOO_SMALL';

			} elseif ( strlen( $uname ) > OZoneSettings::get( 'oz.ofv.const', 'OZ_UNAME_MAX_LENGTH' ) ) {

				$e_msg = 'OZ_FIELD_USER_NAME_TOO_LONG';

			}

			$ofv->addError( $e_msg );
		}
	}