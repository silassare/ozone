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

use OZONE\Tests\TestUtils;
use PHPUnit\Framework\TestCase;

/**
 * Class RouteTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class RouteTest extends TestCase
{
	public function testIsDynamic(): void
	{
		$router = TestUtils::router();

		$foo = $router->getRoute('foo');
		self::assertFalse($foo->isDynamic());

		$baz = $router->getRoute('bar.baz');
		self::assertFalse($baz->isDynamic());

		$articlesList = $router->getRoute('articles.list');
		self::assertFalse($articlesList->isDynamic());

		$articlesGetById = $router->getRoute('articles.get_by_id');
		self::assertTrue($articlesGetById->isDynamic());
	}

	public function testGetPath(): void
	{
		$router = TestUtils::router();

		$foo = $router->getRoute('foo');
		self::assertSame('/foo', $foo->getPath());
		self::assertSame('/foo', $foo->getPath(false));

		$baz = $router->getRoute('bar.baz');
		self::assertSame('/bar/baz', $baz->getPath());
		self::assertSame('/baz', $baz->getPath(false));
		$articlesList = $router->getRoute('articles.list');
		self::assertSame('/articles', $articlesList->getPath());
		self::assertSame('', $articlesList->getPath(false));

		$articlesGetById = $router->getRoute('articles.get_by_id');
		self::assertSame('/articles/:id', $articlesGetById->getPath());
		self::assertSame(':id', $articlesGetById->getPath(false));
	}

	public function testGetParserResult(): void
	{
		$router = TestUtils::router();

		$foo = $router->getRoute('foo');
		self::assertSame('/foo', $foo->getParserResult());

		$baz = $router->getRoute('bar.baz');
		self::assertSame('/bar/baz', $baz->getParserResult());

		$articlesList = $router->getRoute('articles.list');
		self::assertSame('/articles', $articlesList->getParserResult());

		$articlesGetById = $router->getRoute('articles.get_by_id');
		self::assertSame('/articles/(?P<id>[^/]+)', $articlesGetById->getParserResult());

		$userArticles = $router->getRoute('users.by_id.articles');
		self::assertSame('/users/(?P<id>[^/]+)/articles(?:/(?P<state>[^/]+))?', $userArticles->getParserResult());
	}

	public function testBuildPath(): void
	{
		$router  = TestUtils::router();
		$context = TestUtils::context();

		$foo = $router->getRoute('foo');
		self::assertSame('/foo', $foo->buildPath($context));

		$baz = $router->getRoute('bar.baz');
		self::assertSame('/bar/baz', $baz->buildPath($context));

		$articlesList = $router->getRoute('articles.list');
		self::assertSame('/articles', $articlesList->buildPath($context));

		$articlesGetById = $router->getRoute('articles.get_by_id');
		self::assertSame('/articles/1', $articlesGetById->buildPath($context, ['id' => 1]));

		$userArticles = $router->getRoute('users.by_id.articles');
		self::assertSame('/users/1/articles', $userArticles->buildPath($context, ['id' => 1]));
		self::assertSame('/users/1/articles/published', $userArticles->buildPath($context, [
			'id'    => 1,
			'state' => 'published',
		]));
	}
}
