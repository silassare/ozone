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
use OZONE\Tests\TestUtils;
use PHPUnit\Framework\TestCase;

/**
 * Class RouterTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class RouterTest extends TestCase
{
	public function testGetRoute(): void
	{
		$router = TestUtils::router();

		$foo = $router->getRoute('foo');
		self::assertInstanceOf(Route::class, $foo);

		$baz = $router->getRoute('bar.baz');
		self::assertInstanceOf(Route::class, $baz);

		$articlesList = $router->getRoute('articles.list');
		self::assertInstanceOf(Route::class, $articlesList);

		$articlesGetById = $router->getRoute('articles.get_by_id');
		self::assertInstanceOf(Route::class, $articlesGetById);

		$userArticlesList = $router->getRoute('users.by_id.articles');

		self::assertInstanceOf(Route::class, $userArticlesList);
	}

	public function testFind(): void
	{
		$router          = TestUtils::router();
		$articlesGetById = $router->getRoute('articles.get_by_id');

		$result = $router->find('GET', '/articles/42', true);

		$found = $result->found();

		self::assertIsArray($found);
		self::assertSame($articlesGetById, $found['route']);
		self::assertSame('42', $found['params']['id']);

		$userArticlesList = $router->getRoute('users.by_id.articles');
		$result           = $router->find('GET', '/users/1/articles', true);
		$found            = $result->found();
		self::assertIsArray($found);
		self::assertSame($userArticlesList, $found['route']);
		self::assertSame('1', $found['params']['id']);

		$result = $router->find('GET', '/users/5/articles/draft', true);
		$found  = $result->found();
		self::assertIsArray($found);
		self::assertSame($userArticlesList, $found['route']);
		self::assertSame('5', $found['params']['id']);
		self::assertSame('draft', $found['params']['state']);
	}
}
