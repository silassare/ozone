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

use InvalidArgumentException;
use OZONE\Core\Router\Router;
use OZONE\Core\Router\RouteSearchStatus;
use OZONE\Tests\TestUtils;
use PHPUnit\Framework\TestCase;

/**
 * Class RouteTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Router\Route
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

	public function testEmptyName(): void
	{
		$router = TestUtils::router();

		$route = $router->get('/empty-name', static fn () => null);

		self::expectException(InvalidArgumentException::class);
		self::expectExceptionMessage('Route name must be non-empty and not whitespace-only string.');

		$route->name('');
	}

	public function testKeyIsStableAndUniquePerRoute(): void
	{
		$router = TestUtils::router();

		$foo = $router->getRoute('foo');
		$baz = $router->getRoute('bar.baz');

		self::assertSame($foo->key(), $router->getRoute('foo')->key());
		self::assertNotSame($foo->key(), $baz->key());
		self::assertStringStartsWith('foo|', $foo->key());
		self::assertStringEndsWith('|' . $foo->getPath(), $foo->key());
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

	public function testFullPathPrefixIsPrependedToFullPath(): void
	{
		$router = new Router();

		$router->group('/items', static function (Router $r): void {
			$r->get('/:id', static fn () => null)
				->name('get')
				->fullPathPrefix('/v1');
		})
			->name('items');

		$route = $router->getRoute('items.get');

		// getPath(false) returns only the own path segment — prefix does not affect it.
		self::assertSame('/:id', $route->getPath(false));

		// getPath(true) = prefix + parent path + own path.
		self::assertSame('/v1/items/:id', $route->getPath(true));
	}

	public function testFullPathPrefixWithParentRouteOptions(): void
	{
		$router = new Router();

		$parent = $router->get('/api/things', static fn () => null)
			->name('things');

		$child = $router->map(['get'], '/:id', static fn () => null, $parent)
			->name('get')
			->fullPathPrefix('/v2');

		// getPath(false) is not affected by the prefix.
		self::assertSame('/:id', $child->getPath(false));

		// getPath(true) = prefix + parent path + own path.
		self::assertSame('/v2/api/things/:id', $child->getPath(true));
	}

	public function testFullPathPrefixIsIncludedInRoutingPath(): void
	{
		// fullPathPrefix is included in getPath(true), so it IS part of the routing path.
		// A route with fullPathPrefix('/v1') on path '/items/:id' matches /v1/items/42,
		// not /items/42.
		$router = new Router();

		$router->get('/items/:id', static fn () => null)
			->name('with.prefix')
			->fullPathPrefix('/v1');

		// Not found at the unprefixed path.
		$unprefixed = $router->find('GET', '/items/42');
		self::assertSame(RouteSearchStatus::NOT_FOUND, $unprefixed->status());

		// Found at the prefixed path.
		$prefixed = $router->find('GET', '/v1/items/42');
		self::assertSame(RouteSearchStatus::FOUND, $prefixed->status());
	}

	public function testPriority(): void
	{
		$router = TestUtils::router();

		$router->group('/a', static function (Router $router): void {
			$router->group('/b', static function (Router $router): void {
				$router->get('/c', static fn () => null)
					->name('c')->priority(1);
			})
				->name('b')->priority(3);
		})
			->name('a')->priority(1);

		$c = $router->getRoute('a.b.c');

		self::assertSame(1, $c->getOptions()->getPriority(false));
		self::assertSame(5, $c->getOptions()->getPriority(true));
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
		$context = context();

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

		// {brace} param syntax.
		$userGet = $router->getRoute('users.by_id.get');
		self::assertSame('/users/42/', $userGet->buildPath($context, ['id' => 42]));
	}

	public function testBuildPathThrowsOnMissingRequiredParam(): void
	{
		$router  = TestUtils::router();
		$context = context();

		$this->expectException(InvalidArgumentException::class);
		$router->getRoute('articles.get_by_id')->buildPath($context);
	}

	public function testAcceptsPortableParamPatterns(): void
	{
		$router = new Router();

		$patterns = ['[^/]+', '[0-9]+', '[a-z0-9]{32}', '[a-zA-Z0-9_-]+', 'tickets|seats', '[a-z]{1,8}(-[a-z]{1,8})?'];

		foreach ($patterns as $i => $pattern) {
			$router->get('/p' . $i . '/:value', static fn () => null)->param('value', $pattern);
		}

		$router->addGlobalParam('lang', '[a-z]{2}', static fn () => null);

		self::assertCount(6, $router->getRoutes());
	}

	/**
	 * @dataProvider provideRefusesParamPatternsThatAreNotPortableCases
	 */
	public function testRefusesParamPatternsThatAreNotPortable(string $pattern): void
	{
		$router = new Router();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Route parameter "value" pattern');

		$router->get('/p/:value', static fn () => null)->param('value', $pattern);
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function provideRefusesParamPatternsThatAreNotPortableCases(): iterable
	{
		yield 'invalid' => ['[a-z'];

		yield 'anchored at start' => ['^[a-z]+'];

		yield 'anchored at end' => ['[a-z]+$'];

		yield 'possessive quantifier' => ['[a-z]++'];

		yield 'atomic group' => ['(?>ab)'];

		yield 'unescaped delimiter' => ['a~b'];
	}

	public function testRefusesAGlobalParamPatternThatIsNotPortable(): void
	{
		$router = new Router();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Route parameter "lang" pattern');

		$router->addGlobalParam('lang', '[a-z', static fn () => null);
	}

	public function testMatchesAsAPortablePatternIsRun(): void
	{
		$router = new Router();
		$route  = $router->get('/u/:name/:id', static fn () => null)
			->param('name', '.{2}')
			->param('id', '[0-9]+');

		$route = $router->getRoute($route->getName());

		self::assertNotNull($route);
		self::assertSame('~^/u/(?P<name>.{2})/(?P<id>[0-9]+)$~uD', $route->getRegExp());

		// Characters, not bytes: "é" is one character and two bytes.
		self::assertTrue($route->is('/u/éa/1'));
		self::assertFalse($route->is('/u/é/1'));

		// `$` is the end of the path, not also before a final newline.
		self::assertFalse($route->is("/u/ab/1\n"));
		self::assertTrue($route->is('/u/ab/1'));
	}
}
