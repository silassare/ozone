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
use OZONE\Core\Forms\Services\ResumableFormService;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Exposes ResumableFormService::requireCompletion() and ::dropSession()
 * as plain HTTP routes so integration tests can exercise them.
 *
 * Routes registered:
 *  GET  /test/require-completion/:ref  -- calls requireCompletion(); returns {values: ...}
 *  POST /test/drop-session/:ref        -- calls dropSession();       returns {dropped: true}
 */
final class TestFormConsumerRoutesProvider extends BaseService
{
	public static function registerRoutes(Router $router): void
	{
		$router->get('/test/require-completion/:ref', static function (RouteInfo $ri) {
			$s         = new self($ri);
			$form_data = ResumableFormService::requireCompletion($ri->param('ref'), $ri->getContext());
			$s->json()->setDone()->setData(['values' => $form_data->toArray()]);

			return $s->respond();
		})->name('test:require-completion');

		$router->post('/test/drop-session/:ref', static function (RouteInfo $ri) {
			$s = new self($ri);
			ResumableFormService::dropSession($ri->param('ref'));
			$s->json()->setDone()->setData(['dropped' => true]);

			return $s->respond();
		})->name('test:drop-session');
	}

	public static function apiDoc(ApiDoc $doc): void {}
}
