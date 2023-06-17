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

namespace OZONE\Tests\Router;

use OZONE\Core\Router\Route;
use OZONE\Core\Router\Router;
use OZONE\Tests\TestUtils;
use PHPUnit\Framework\TestCase;

/**
 * Class RouteGroupTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class RouteGroupTest extends TestCase
{
	public function testSubGroupRouteName(): void
	{
		$router    = TestUtils::router();
		$bar_group = null;
		$router->group('/test', function (Router $router) use (&$bar_group) {
			$router->get('/foo', static fn () => null)
				->name('foo');
			$bar_group = $router->group('/bar', static function (Router $router) {
				$router->get('/baz', static fn () => null)
					->name('baz');
			});
		})
			->name('test');

		self::assertInstanceOf(Route::class, $router->getRoute('test.foo'));
		// notice that the name is not test.bar.baz as the group with path '/bar' is not named
		self::assertInstanceOf(Route::class, $router->getRoute('test.baz'));

		$bar_group->name('bar');

		// the name should have been updated
		self::assertInstanceOf(Route::class, $router->getRoute('test.bar.baz'));
		self::assertNull($router->getRoute('test.baz'));
	}

	public function testSubGroupRoutePath(): void
	{
		$router = TestUtils::router();

		$router->group('/test', static function (Router $router) {
			$router->get('/foo', static fn () => null)
				->name('foo');
			$router->group('/bar', static function (Router $router) {
				$router->get('/baz', static fn () => null)
					->name('baz');
			});
		})
			->name('test');

		self::assertSame('/test/foo', $router->getRoute('test.foo')
			->getPath());
		self::assertSame('/test/bar/baz', $router->getRoute('test.baz')
			->getPath());
	}
}
