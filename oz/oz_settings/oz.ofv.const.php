<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	return [
		'OZ_UNWANTED_CHAR_REG' => '#[\t\r]|[ ]{2,}#',
		'OZ_EXCLUDE_KEY_WORDS' => '#^ozone$#i',

		'OZ_USER_MIN_AGE' => 16,
		'OZ_USER_MAX_AGE' => 77,

		'OZ_CODE_REG' => '#^[0-9]{6}$#',

		'OZ_PASS_MIN_LENGTH' => 6,
		'OZ_PASS_MAX_LENGTH' => 60,

		'OZ_USER_NAME_REG'        => '#^.+$#',
		'OZ_USER_NAME_MIN_LENGTH' => 3,
		'OZ_USER_NAME_MAX_LENGTH' => 60
	];