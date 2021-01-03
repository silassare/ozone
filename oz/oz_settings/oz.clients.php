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
		/*
		 * how can we get stuff from OZone if set to:
		 *
		 * - any
		 *     - allow CORS for any host
		 *
		 * - check
		 *     - the host of the request header 'Origin' must be the same as the host of the api key client url
		 *
		 * - deny
		 *    - disable CORS
		 *
		 * - default: check (even if you mistype the value)
		 */
		'OZ_CORS_ALLOW_RULE' => 'check',
	];
