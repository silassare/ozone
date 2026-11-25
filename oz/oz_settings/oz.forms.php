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

	/**
	 * Form discoveries (`X-OZONE-Form-Discovery`) of a rate-limited route a client IP may make within
	 * `OZ_FORM_DISCOVERY_IP_INTERVAL` seconds, per route.
	 *
	 * A discovery answers the route's form description and runs nothing of the route, so it is never
	 * counted against the route's own limit (a page showing the sign-in form does not spend a sign-in
	 * attempt); it is counted against this one, looser since it costs the server little, which still
	 * bounds how often a form may be asked for. 0 counts none. A route with no rate limit counts no
	 * discovery either.
	 *
	 * @default 120
	 */
	'OZ_FORM_DISCOVERY_IP_RATE'     => 120,

	/**
	 * Length in seconds of the per-IP window of `OZ_FORM_DISCOVERY_IP_RATE`.
	 *
	 * @default 60 (1 minute)
	 */
	'OZ_FORM_DISCOVERY_IP_INTERVAL' => 60,
];
