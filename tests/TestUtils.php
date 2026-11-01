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

use OZONE\Core\Forms\Field;
use OZONE\Core\Forms\Form;
use OZONE\Core\Router\Route;
use OZONE\Core\Router\Router;
use PHPUnit\Framework\Assert;

/**
 * Class TestUtils.
 */
class TestUtils
{
	/**
	 * The field of that name, which the form must have.
	 *
	 * `Form::getField()` answers null for a name it does not hold, and a test that walks straight
	 * into the answer reports a field it never declared as a call on null.
	 */
	public static function field(Form $form, string $name): Field
	{
		$field = $form->getField($name);

		Assert::assertNotNull($field, \sprintf('The form has no field named "%s".', $name));

		return $field;
	}

	/**
	 * The route of that name, which the router must have.
	 *
	 * `Router::getRoute()` answers null for a name it does not know, and a test that walks straight
	 * into the answer reports a route it never declared as a call on null, three frames from the
	 * name it got wrong. Asked for here, the name is in the failure.
	 */
	public static function route(Router $router, string $name): Route
	{
		$route = $router->getRoute($name);

		Assert::assertNotNull($route, \sprintf('The router has no route named "%s".', $name));

		return $route;
	}

	/**
	 * Returns a mock router.
	 */
	public static function router(): Router
	{
		$router = new Router();

		$router->get('/foo', static fn () => null)
			->name('foo');

		$router->group('/bar', static function (Router $router): void {
			$router->get('/baz', static fn () => null)
				->name('baz');
		})
			->name('bar');

		$router->group('/users', static function (Router $router): void {
			$router->group('/{id}/', static function (Router $router): void {
				$router->get(static fn () => null)
					->name('get');

				$router->get('/articles[/:state]', static fn () => null)
					->name('articles');
			})
				->name('by_id');
		})
			->name('users');

		$router->group('/articles', static function (Router $router): void {
			$router->get(static fn () => null)
				->name('list');
			$router->get(':id', static fn () => null)
				->name('get_by_id');
		})
			->name('articles');

		return $router;
	}
}
