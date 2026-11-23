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
 * A service of the `api` scope living in the scope's own directory (`scopes/api/`), enabled by the
 * scope's own settings only: what another scope calling it as nobody has to find.
 */
final class AnonCallScopedService extends BaseService
{
	public static function registerRoutes(Router $router): void
	{
		$router->get('/anon-call/scoped', static function (RouteInfo $ri) {
			$s = new self($ri);
			$s->json()->setDone()->setData(['scoped' => true, 'scope' => scope()->getName()]);

			return $s->respond();
		})->name('anoncall:scoped');
	}

	public static function apiDoc(ApiDoc $doc): void {}
}
