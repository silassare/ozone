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
 * A route of the `www` scope that calls a service of the `api` scope as nobody, and hands back what
 * it was answered, next to whether its own visitor is signed in.
 */
final class AnonCallWebPage extends BaseService
{
	public static function registerRoutes(Router $router): void
	{
		$router->get('/anon-call/proxy/:service', static function (RouteInfo $ri) {
			$context = $ri->getContext();
			$inner   = $context->callAnonymously('anoncall:' . $ri->param('service'), [], [], 'api');

			$s = new self($ri);
			$s->json()->setDone()->setData([
				'caller_signed_in' => $context->hasAuthenticatedUser(),
				'caller_scope'     => scope()->getName(),
				'status'           => $inner->getStatusCode(),
				'body'             => (string) $inner->getBody(),
			]);

			return $s->respond();
		})->name('anoncall:proxy')->param('service', '[a-z]+');
	}

	public static function apiDoc(ApiDoc $doc): void {}
}
