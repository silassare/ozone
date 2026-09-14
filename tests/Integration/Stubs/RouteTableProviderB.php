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
use OZONE\Core\App\Settings;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Logs each registration, like RouteTableProviderA, and links to one of its routes by name.
 */
final class RouteTableProviderB extends BaseService
{
	public static function registerRoutes(Router $router): void
	{
		\file_put_contents('__PLH_LOG_FILE__', 'B' . \PHP_EOL, \FILE_APPEND | \LOCK_EX);

		$router->get('/rt-b', static function (RouteInfo $ri) {
			$s = new self($ri);
			$s->json()->setDone()->setData(['provider' => 'B']);

			return $s->respond();
		})->name('rt:b');

		$router->get('/rt-b/link', static function (RouteInfo $ri) {
			$s = new self($ri);
			$s->json()->setDone()->setData(['link' => (string) $ri->getContext()->buildRouteUri('rt:a')]);

			return $s->respond();
		})->name('rt:b.link');

		// A setting as this request sees it, read nowhere earlier in the request.
		$router->get('/rt-b/setting', static function (RouteInfo $ri) {
			$s = new self($ri);
			$s->json()->setDone()->setData(['value' => Settings::get('oz.gc', 'OZ_GC_PROBABILITY')]);

			return $s->respond();
		})->name('rt:b.setting');
	}

	public static function apiDoc(ApiDoc $doc): void {}
}
