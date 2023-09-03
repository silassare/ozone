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

namespace OZONE\Tests;

use OZONE\Core\App\Context;
use OZONE\Core\OZone;
use OZONE\Core\Router\Router;
use OZONE\Tests\App\App;

/**
 * Class TestUtils.
 */
class TestUtils
{
	/**
	 * Returns a mock context.
	 *
	 * @return \OZONE\Core\App\Context
	 */
	public static function context(): Context
	{
		static $context = null;

		if (null === $context) {
			OZone::run(new App());
			$context = OZone::getMainContext();
		}

		return $context;
	}

	/**
	 * Returns a mock router.
	 *
	 * @return \OZONE\Core\Router\Router
	 */
	public static function router(): Router
	{
		$router = new Router();

		$router->get('/foo', static fn () => null)
			->name('foo');

		$router->group('/bar', static function (Router $router) {
			$router->get('/baz', static fn () => null)
				->name('baz');
		})
			->name('bar');

		$router->group('/users', static function (Router $router) {
			$router->group('/{id}/', static function (Router $router) {
				$router->get(static fn () => null)
					->name('get');

				$router->get('/articles[/:state]', static fn () => null)
					->name('articles');
			})
				->name('by_id');
		})
			->name('users');

		$router->group('/articles', static function (Router $router) {
			$router->get(static fn () => null)
				->name('list');
			$router->get(':id', static fn () => null)
				->name('get_by_id');
		})
			->name('articles');

		return $router;
	}
}