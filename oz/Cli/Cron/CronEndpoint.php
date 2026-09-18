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

namespace OZONE\Core\Cli\Cron;

use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\Interfaces\RouteProviderInterface;
use OZONE\Core\Router\Rates\IPRateLimit;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Class CronEndpoint.
 *
 * The `oz:cron` route (`/oz-cron`, GET or POST): an external service (cron-job.org, a host's URL cron,
 * an uptime monitor) calls it every minute to run the due tasks, on a host with no scheduler and
 * little traffic. It answers at once; the tick runs once the response is sent ({@see CronRunner}).
 *
 * Mapped only when `OZ_CRON_WEB_KEY` (`oz.cron`) is set, and the key has to come with the request, in
 * the `X-OZONE-Cron-Key` header or a `key` body field: never in the URL, which logs keep.
 */
final class CronEndpoint implements RouteProviderInterface
{
	public const ROUTE = 'oz:cron';

	/**
	 * A public path: the internal ones (`OZone::INTERNAL_PATH_PREFIX`) only answer sub-requests.
	 */
	public const PATH = '/oz-cron';

	public const KEY_HEADER = 'X-OZONE-Cron-Key';

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function registerRoutes(Router $router): void
	{
		if ('' === self::key()) {
			return;
		}

		$router->map(['GET', 'POST'], self::PATH, static function (RouteInfo $ri): Response {
			$context = $ri->getContext();
			$request = $context->getRequest();
			$given   = $request->getHeaderLine(self::KEY_HEADER);

			if ('' === $given) {
				$body  = $request->getParsedBody();
				$given = \is_array($body) && \is_string($body['key'] ?? null) ? $body['key'] : '';
			}

			$key = self::key();

			if ('' === $key || !\hash_equals($key, $given)) {
				throw new ForbiddenException();
			}

			CronRunner::tickAfterResponse();

			return $context->getResponse()->withJson(['accepted' => true], 202);
		})
			->name(self::ROUTE)
			// Called by a service, with no session: nothing for a CSRF token to protect.
			->withoutCSRF()
			->rateLimit(static fn (RouteInfo $ri) => new IPRateLimit(
				$ri,
				(int) Settings::get('oz.cron', 'OZ_CRON_WEB_IP_RATE'),
				(int) Settings::get('oz.cron', 'OZ_CRON_WEB_IP_INTERVAL')
			));
	}

	private static function key(): string
	{
		return (string) Settings::get('oz.cron', 'OZ_CRON_WEB_KEY', '');
	}
}
