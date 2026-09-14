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

namespace OZONE\Core\Runtime\Bridges;

use OZONE\Core\App\Interfaces\AppInterface;
use OZONE\Core\OZone;
use OZONE\Core\Runtime\Runtime;
use OZONE\Core\Runtime\WorkerRuntime;

/**
 * Class FrankenPhpBridge.
 *
 * Serves OZone from a FrankenPHP worker script:
 *
 * ```php
 * // public/api/worker.php, run with `frankenphp php-server --worker public/api/worker.php`
 * // after the same set-up as the scope's index.php (boot file, scope settings and templates).
 * FrankenPhpBridge::serve($app);
 * ```
 *
 * FrankenPHP resets the superglobals, `php://input` and the headers for each request, so OZone
 * reads the request and writes the response exactly as under PHP-FPM: no response sink is needed.
 */
final class FrankenPhpBridge
{
	/**
	 * Bootstraps once, then serves requests until FrankenPHP stops the worker.
	 *
	 * @param AppInterface $app          the application
	 * @param int          $max_requests requests to serve before the worker exits and FrankenPHP
	 *                                   starts a fresh one (0: no limit); a guard against slow
	 *                                   leaks in application code
	 */
	public static function serve(AppInterface $app, int $max_requests = 0): void
	{
		Runtime::set(new WorkerRuntime('frankenphp'));

		// A client going away must not interrupt the worker in the middle of a request.
		\ignore_user_abort(true);

		OZone::bootstrap($app);

		// The superglobals are the request's inside the callback only, which is where
		// `OZone::handleRequest()` reads them.
		$handler = static function (): void {
			OZone::handleRequest();
		};

		// No gc_collect_cycles() per request: it cost ~40% of a request's time, for the few cycles a
		// request leaves, which PHP's own collector reclaims in batches anyway.
		for ($served = 0; 0 === $max_requests || $served < $max_requests; ++$served) {
			if (!frankenphp_handle_request($handler)) {
				break;
			}
		}
	}
}
