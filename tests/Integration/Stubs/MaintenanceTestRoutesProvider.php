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
 * A route of both routers that says which scope served it, for a test to tell a request a
 * maintenance held from one it let through.
 */
final class MaintenanceTestRoutesProvider extends BaseService
{
	public static function registerRoutes(Router $router): void
	{
		$router->get('/mt-ping', static function (RouteInfo $ri) {
			$s = new self($ri);
			$s->json()->setDone()->setData(['scope' => scope()->getName()]);

			return $s->respond();
		})->name('mt:ping');
	}

	public static function apiDoc(ApiDoc $doc): void {}
}
