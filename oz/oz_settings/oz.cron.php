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

use OZONE\Core\Cli\Cron\CronEndpoint;
use OZONE\Core\Cli\Cron\CronRunner;

/**
 * Who runs the due cron tasks ({@see CronRunner}).
 */
return [
	/**
	 * - `auto`: a scheduler -- `oz cron run` every minute (crontab, a systemd timer, a host's cron
	 *   panel) or the `oz cron work` process -- and, while none has run for
	 *   `OZ_CRON_SCHEDULER_TIMEOUT` seconds, the requests: the first request of a minute runs the due
	 *   tasks once its response is sent. Nothing to set up on a host without a scheduler, and requests
	 *   take over when a server's scheduler stops.
	 * - `scheduler`: the scheduler only; a request never runs a task.
	 * - `requests`: the requests, whatever else runs.
	 *
	 * A tick also runs the minutes no tick ran (an hour back at most), so a task due in a minute
	 * without a request runs at the next one.
	 *
	 * @default 'auto'
	 */
	'OZ_CRON_RUNNER' => 'auto',

	/**
	 * Seconds without a scheduler before requests run the due tasks (`auto`).
	 *
	 * @default 120
	 */
	'OZ_CRON_SCHEDULER_TIMEOUT' => 120,

	/**
	 * When requests run the due tasks: at most one tick per this many seconds on a server (60 at
	 * least, the resolution of a schedule). Longer spares a busy host a tick a minute; the tasks due
	 * in between run at the next tick, which catches up the minutes no tick ran.
	 *
	 * @default 60
	 */
	'OZ_CRON_REQUESTS_INTERVAL' => 60,

	/**
	 * The secret with which an external service (cron-job.org, a host's URL cron, an uptime monitor)
	 * runs the due tasks every minute, through `/oz-cron` (the `oz:cron` route, {@see CronEndpoint}): for a host
	 * with no scheduler and little traffic. Sent in the `X-OZONE-Cron-Key` header or a `key` body
	 * field, never in the URL. Empty: there is no such route. Set it in `.env`.
	 *
	 * @default ''
	 */
	'OZ_CRON_WEB_KEY' => env('OZ_CRON_WEB_KEY', ''),

	/**
	 * Calls of `/oz-cron` allowed per client IP within `OZ_CRON_WEB_IP_INTERVAL` seconds.
	 *
	 * The route is called once a minute by one service, so the default leaves room for a retry and
	 * nothing more; the key is what protects it, this only caps what a caller can cost.
	 *
	 * @default 10
	 */
	'OZ_CRON_WEB_IP_RATE' => 10,

	/**
	 * Length in seconds of the per-IP window of `/oz-cron`.
	 *
	 * @default 60
	 */
	'OZ_CRON_WEB_IP_INTERVAL' => 60,
];
