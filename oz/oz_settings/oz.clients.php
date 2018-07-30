<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	return [
		/**
		 * how can we get stuff from OZone if set to:
		 *
		 *  - any
		 *      request should contains:
		 *          - a valid api key
		 *
		 *  - check
		 *        request should contains:
		 *          - a valid api key
		 *          - an Origin header that is the same as the url of the api key owner
		 *
		 * - default is always (even if you mistype the value): check
		 */
		'OZ_CORS_ALLOW_RULE' => 'check'
	];