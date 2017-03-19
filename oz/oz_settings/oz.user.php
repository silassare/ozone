<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	\OZONE\OZ\Core\OZoneSettings::set( 'oz.user', array(
		//class used to instantiate user object, must extends OZoneUserBase 
		'OZ_USER_CLASS'     => 'OZONE\OZ\User\OZoneUser',
		//id par defaut s'il n'y en a pas
		'OZ_DEFAULT_PICID'  => '0_0',
		//taille maximale de crop d'une image de profile: en pixels
		'OZ_PPIC_MIN_SIZE'  => 150,
		//taille maximale d'un thumbnail: en pixels
		'OZ_THUMB_MAX_SIZE' => 640
	) );