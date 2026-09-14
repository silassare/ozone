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

namespace __PLH_NAMESPACE__;

use OZONE\Core\App\Service as BaseService;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * A route provider that logs each time a router registers it, which a router routing through a
 * route table does only when a request needs one of its routes.
 */
final class RouteTableProviderA extends BaseService
{
	public static function registerRoutes(Router $router): void
	{
		\file_put_contents('__PLH_LOG_FILE__', 'A' . \PHP_EOL, \FILE_APPEND | \LOCK_EX);

		$router->get('/rt-a', static function (RouteInfo $ri) {
			$s = new self($ri);
			$s->json()->setDone()->setData(['provider' => 'A']);

			return $s->respond();
		})->name('rt:a');
	}

	public static function apiDoc(ApiDoc $doc): void {}
}
