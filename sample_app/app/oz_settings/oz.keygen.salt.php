<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	\OZONE\OZ\Core\OZoneSettings::set( 'oz.keygen.salt', array(
		//sel utilisé pour générer les clefs des fichiers
		'OZ_FKEY_GEN_SALT'   => '__f!le29% @ozone',
		//sel utilisé pour générer les identifiants de sessions
		'OZ_SID_GEN_SALT'    => '__oz__s@lt @ozone__229',
		//sel utilisé pour générer les tokens d'authentification
		'OZ_AUTH_TOKEN_SALT' => '_!ozone!__ @token_6%',
		//sel utilisé pour générer les identifiants de client
		'OZ_CLID_GEN_SALT'   => '%_oz_cl!d @one'
	) );