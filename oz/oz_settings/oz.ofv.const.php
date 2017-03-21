<?php
	/**
	 * This file is part of the Ozone package.
	 *
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	\OZONE\OZ\Core\OZoneSettings::set( 'oz.ofv.const', array(

		'OZ_UNWANTED_CHAR_REG' => '#[\t\r]|[ ]{2,}#',
		'OZ_EXCLUDE_KEY_WORDS' => '#^ozone$#i',

		//l'age minimum requis pour s'inscrire: en annees
		'OZ_USER_MIN_AGE'      => 16,
		//l'age maximum requis pour s'inscrire: en annees
		'OZ_USER_MAX_AGE'      => 77,

		'OZ_CODE_REG' => '#^[0-9]{6}$#',

		'OZ_SEX_REG'         => '#^(?:H|F)$#',
		'OZ_PASS_MIN_LENGTH' => 6,
		'OZ_PASS_MAX_LENGTH' => 15,

		'OZ_UNAME_REG'        => '#^.{3,60}$#',
		'OZ_UNAME_MIN_LENGTH' => 3,
		'OZ_UNAME_MAX_LENGTH' => 60,

		/**
		 * Email matching regex
		 *
		 * source: http://www.w3.org/TR/html5/forms.html#valid-e-mail-address
		 *		- TLD not required
		 *			/^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/
		 * 		- must have TLD
		 * 			/^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/
		 */

		'OZ_EMAIL_REG'	=> '#^[a-zA-Z0-9.!#$%&\'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$#'
	) );