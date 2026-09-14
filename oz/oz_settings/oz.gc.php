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
	 * Garbage collection (expired sessions, auth entities, temporary files, cache entries) runs
	 * hourly, as the `oz:gc` cron task. While no scheduler runs cron -- `oz cron run`, `oz cron work`
	 * or the `oz:cron` route, none for an hour (`oz.cron`) -- it also runs after the response of 1
	 * request in this many. 0: never on requests.
	 *
	 * @default 100
	 */
	'OZ_GC_PROBABILITY' => 100,
];
