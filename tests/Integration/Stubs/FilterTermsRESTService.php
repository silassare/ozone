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

use OZONE\Core\REST\RESTFulService;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * The REST service of the `terms` table of RESTFilterRefusalTest, and `GET /terms-seed`, which writes
 * its rows.
 */
final class FilterTermsRESTService extends RESTFulService
{
	public const SERVICE_NAME = 'terms';
	public const SERVICE_PATH = '/terms';
	public const TABLE_NAME   = 'terms';

	public static function registerRoutes(Router $router): void
	{
		self::registerRESTRoutes($router);

		$router->get('/terms-seed', static function (RouteInfo $ri) {
			foreach ([['format', true], ['format', false], ['topic', true]] as [$kind, $active]) {
				$term = new Db\Term();
				$term->setKind($kind)->setIsActive($active)->save();
			}

			$s = new self($ri);
			$s->json()->setDone();

			return $s->respond();
		})->name('test:terms:seed');
	}
}
