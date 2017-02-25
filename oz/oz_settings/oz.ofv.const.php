<?php

	OZoneSettings::set( 'oz.ofv.const', array(

		"OZ_UNWANTED_CHAR_REG" => '#[\t\r]|[ ]{2,}#',
		"OZ_EXCLUDE_KEY_WORDS" => '#^ozone$#i',

		//l'age minimum requis pour s'inscrire: en annees
		"OZ_USER_MIN_AGE"      => 16,
		//l'age maximum requis pour s'inscrire: en annees
		"OZ_USER_MAX_AGE"      => 77,

		"OZ_SEXE_REG"        => '#^(?:H|F)$#',
		"OZ_PASS_MIN_LENGTH" => 6,
		"OZ_PASS_MAX_LENGTH" => 15,

		"OZ_UNAME_REG"        => "#^.{3,60}$#",
		"OZ_UNAME_MIN_LENGTH" => 3,
		"OZ_UNAME_MAX_LENGTH" => 60
	) );