<?php

	OZoneSettings::set( 'oz.user', array(
		//class used to instanciate user object, must extends OZoneUserBase 
		"OZ_USER_CLASS"     => "OZoneUser",
		//id par defaut s'il n'y en a pas
		"OZ_DEFAULT_PICID"  => '0_0',
		//taille maximale de crop d'une image de profile: en pixels
		"OZ_PPIC_MIN_SIZE"  => 150,
		//taille maximale d'un thumbnail: en pixels
		"OZ_THUMB_MAX_SIZE" => 640
	) );