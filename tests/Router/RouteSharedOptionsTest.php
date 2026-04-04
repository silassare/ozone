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

use OZONE\Core\Forms\Form;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\RouteSharedOptions;
use OZONE\Tests\TestUtils;
use PHPUnit\Framework\TestCase;

/**
 * Class RouteSharedOptionsTest.
 *
 * Tests for runtime behaviour of {@see RouteSharedOptions},
 * specifically the bundle assembly in {@see RouteSharedOptions::getFormBundle()}.
 *
 * @internal
 *
 * @coversNothing
 */
final class RouteSharedOptionsTest extends TestCase
{
	public function testGetFormBundleReturnsNullWithNoForm(): void
	{
		$router = TestUtils::router();
		$route  = $router->getRoute('foo');
		$ri     = new RouteInfo(context(), $route, []);

		self::assertNull($route->getOptions()->getFormBundle($ri));
	}

	public function testGetFormBundleSetsSubmitToFromRequest(): void
	{
		$router = TestUtils::router();
		$form   = new Form();
		$form->field('name')->required(true);

		$router->post('/bundle-test', static fn () => null)
			->name('bundle.test')
			->form($form);

		$route  = $router->getRoute('bundle.test');
		$ri     = new RouteInfo(context(), $route, []);
		$bundle = $route->getOptions()->getFormBundle($ri);

		self::assertNotNull($bundle);
		self::assertNotNull($bundle->getSubmitTo());
		self::assertSame(
			(string) context()->getRequest()->getUri(),
			(string) $bundle->getSubmitTo()
		);
	}

	public function testGetFormBundleSetsMethodFromRequest(): void
	{
		$router = TestUtils::router();
		$form   = new Form();
		$form->field('name')->required(true);

		$router->post('/bundle-method-test', static fn () => null)
			->name('bundle.method.test')
			->form($form);

		$route  = $router->getRoute('bundle.method.test');
		$ri     = new RouteInfo(context(), $route, []);
		$bundle = $route->getOptions()->getFormBundle($ri);

		self::assertNotNull($bundle);
		self::assertSame(context()->getRequest()->getMethod(), $bundle->getMethod());
	}
}
