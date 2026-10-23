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
 * Routes that read and write the session store, with a slow one to overlap requests.
 */
final class SessionConcurrencyRoutesProvider extends BaseService
{
	private const int SLOW_MS = 1500;

	public static function registerRoutes(Router $router): void
	{
		$router->get('/test-session-set', static function (RouteInfo $ri) {
			$ri->getContext()->requireAuthStore()->set('uid', 'u1');

			return self::answer($ri, ['set' => true]);
		})->name('test:session:set');

		$router->get('/test-session-remove', static function (RouteInfo $ri) {
			$ri->getContext()->requireAuthStore()->remove('uid');

			return self::answer($ri, ['removed' => true]);
		})->name('test:session:remove');

		$router->get('/test-session-dump', static function (RouteInfo $ri) {
			$store = $ri->getContext()->requireAuthStore();

			return self::answer($ri, ['data' => $store->getData()]);
		})->name('test:session:dump');

		// Reads the session, then takes its time answering: another request runs meanwhile.
		$router->get('/test-session-slow-read', static function (RouteInfo $ri) {
			$store = $ri->getContext()->requireAuthStore();
			$seen  = $store->get('uid');

			\usleep(self::SLOW_MS * 1000);

			return self::answer($ri, ['seen' => $seen]);
		})->name('test:session:slow-read');

		// Rotating the session ID on sign-out, as a privilege change asks for, instead of removing
		// the key from the session the client already holds.
		$router->get('/test-session-remove-rotate', static function (RouteInfo $ri) {
			$auth = $ri->getContext()->requireStatefulAuth();
			$kept = $auth->store()->getData();

			unset($kept['uid']);

			$auth->renew();
			$auth->store()
				->merge($kept)
				->set('signed_out', true);

			return self::answer($ri, ['removed' => true]);
		})->name('test:session:remove-rotate');

		// Same, but writes a key of its own before answering.
		$router->get('/test-session-slow-write', static function (RouteInfo $ri) {
			$store = $ri->getContext()->requireAuthStore();
			$seen  = $store->get('uid');

			\usleep(self::SLOW_MS * 1000);

			$store->set('lang', 'fr');

			return self::answer($ri, ['seen' => $seen]);
		})->name('test:session:slow-write');
	}

	public static function apiDoc(ApiDoc $doc): void {}

	private static function answer(RouteInfo $ri, array $data): mixed
	{
		$s = new self($ri);
		$s->json()
			->setDone()
			->setData($data);

		return $s->respond();
	}
}
