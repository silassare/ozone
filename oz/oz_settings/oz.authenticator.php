<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [
	// nombre maximum de tentative sur un code d'authentification reçu par sms (ou mail? lol)
	'OZ_AUTH_CODE_TRY_MAX'           => 3,
	// temps maximum de validité du code d'authentification (en secondes)
	'OZ_AUTH_CODE_LIFE_TIME'         => 60 * 60,
	'OZ_AUTH_ACCOUNT_COOKIE_ENABLED' => false,
	'OZ_AUTH_ACCOUNT_COOKIE_NAME'    => 'OZ_ACCOUNT_AUTH',
];
