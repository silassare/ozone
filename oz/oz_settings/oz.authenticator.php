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
		// nombre maximum de tentative sur un code d'authentification reçu par sms (ou mail? lol)
		'OZ_AUTH_CODE_TRY_MAX'   => 3,
		// temps maximum de validité du code d'authentification (en secondes)
		'OZ_AUTH_CODE_LIFE_TIME' => 60 * 60
	];