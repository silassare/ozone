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
 * The sign-in page of a project, as `OZ_AUTH_SIGN_IN_ROUTE` names one.
 *
 * Only its name and its path are of interest: the pages of an authorization link link to it, and the
 * test reads the link rather than following it.
 */
final class SignInPageRoutesProvider extends BaseService
{
	public static function registerRoutes(Router $router): void
	{
		$router->get('/sign-in', static function (RouteInfo $ri) {
			$s = new self($ri);
			$s->json()->setDone();

			return $s->respond();
		})->name('app:sign-in');
	}

	public static function apiDoc(ApiDoc $doc): void {}
}
