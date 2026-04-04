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
use OZONE\Core\Forms\Form;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Custom route provider injected by integration tests.
 */
final class TestRoutesProvider extends BaseService
{
	public static function registerRoutes(Router $router): void
	{
		// Simple GET route.
		$router->get('/test-ping', static function (RouteInfo $ri) {
			$s = new self($ri);
			$s->json()->setDone('PONG')->setData(['pong' => true]);

			return $s->respond();
		})->name('test:ping');

		// POST route with required form field.
		$router->post('/test-echo', static function (RouteInfo $ri) {
			$s = new self($ri);
			$s->json()->setDone()->setData(['message' => $ri->getCleanFormData()->get('message')]);

			return $s->respond();
		})->name('test:echo')->form(static function () {
			$form = new Form();
			$form->string('message', true);

			return $form;
		});

		// GET route guarded by authentication requirement.
		$router->get('/test-protected', static function (RouteInfo $ri) {
			$s = new self($ri);
			$s->json()->setDone()->setData(['secret' => 'ok']);

			return $s->respond();
		})->name('test:protected')->withAuthenticatedUser();
	}

	public static function apiDoc(ApiDoc $doc): void {}
}
