<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

return [
	/**
	 * Sessions of a resumable form a client IP may open within `OZ_FORM_RESUME_INIT_IP_INTERVAL`
	 * seconds, through the standalone services (`/form/:provider/init`).
	 *
	 * Opening one costs the server what it keeps until the form is finished or expires, so this caps
	 * what an anonymous caller can hold. Only `init` is limited: the steps of a session already opened
	 * are not, and several people behind one address share the count.
	 *
	 * @default 30
	 */
	'OZ_FORM_RESUME_INIT_IP_RATE' => 30,

	/**
	 * Length in seconds of the per-IP window of `/form/:provider/init`.
	 *
	 * @default 3600 (1 hour)
	 */
	'OZ_FORM_RESUME_INIT_IP_INTERVAL' => 3600,
];
