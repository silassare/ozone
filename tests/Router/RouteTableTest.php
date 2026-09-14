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

use OZONE\Core\Router\Events\RouterCreated;
use OZONE\Core\Router\Route;
use OZONE\Core\Router\Router;
use OZONE\Core\Router\RouteSearchResult;
use OZONE\Core\Router\RouteSearchStatus;
use OZONE\Core\Router\RouteTable;
use OZONE\Tests\Router\Stubs\TableItemProvider;
use OZONE\Tests\Router\Stubs\TableItemsProvider;
use OZONE\Tests\Router\Stubs\TableLangProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OZONE\Core\Router\Router
 * @covers \OZONE\Core\Router\RouteTable
 *
 * @internal
 */
final class RouteTableTest extends TestCase
{
	private const PROVIDERS = [
		TableItemsProvider::class => true,
		TableItemProvider::class  => true,
		TableLangProvider::class  => true,
		'Disabled\Provider'       => false,
	];

	protected function setUp(): void
	{
		parent::setUp();

		self::resetRegistrations();
	}

	public function testRoutesRequestsAsARouterRegisteringEverything(): void
	{
		$full  = self::router();
		$table = $full->compileTable();

		$requests = [
			['GET', '/items'],
			['POST', '/items'],
			['PUT', '/items'],
			['GET', '/items/latest'],
			['GET', '/items/42'],
			['DELETE', '/items/42'],
			['PATCH', '/items/42'],
			['GET', '/items/x-foo'],
			['GET', '/items/some-slug'],
			['GET', '/anon'],
			['POST', '/anon'],
			['GET', '/en/about'],
			['GET', '/eng/about'],
			['GET', '/refined'],
			['POST', '/refined/sibling'],
			['GET', '/nowhere'],
			['FOO', '/items'],
		];

		foreach ($requests as [$method, $path]) {
			self::assertSame(
				self::describe($full->find($method, $path)),
				self::describe(self::router($table)->find($method, $path)),
				$method . ' ' . $path
			);
		}
	}

	public function testRegistersWhatARequestNeeds(): void
	{
		$table = self::router()->compileTable();

		self::resetRegistrations();

		$router = self::router($table);

		// The provider adding a global parameter, which what every route matches depends on.
		self::assertSame([0, 0, 1], self::registrations());

		self::assertSame(RouteSearchStatus::NOT_FOUND, $router->find('GET', '/nowhere')->status());
		self::assertSame(RouteSearchStatus::METHOD_NOT_ALLOWED, $router->find('PUT', '/items')->status());
		self::assertSame([0, 0, 1], self::registrations());

		self::assertSame('item.get', $router->find('GET', '/items/42')->foundRoute()->getName());
		self::assertSame([0, 1, 1], self::registrations());

		self::assertSame('items.create', $router->getRoute('items.create')?->getName());
		self::assertSame([1, 1, 1], self::registrations());
	}

	public function testAnUnknownNameRegistersNothing(): void
	{
		$router = self::router(self::router()->compileTable());

		self::resetRegistrations();

		self::assertNull($router->getRoute('nope'));
		self::assertSame([0, 0, 0], self::registrations());
	}

	public function testListingTheRoutesRegistersEveryProvider(): void
	{
		$full   = self::router();
		$router = self::router($full->compileTable());

		self::assertSame(self::keys($full->getRoutes()), self::keys($router->getRoutes()));
	}

	public function testAutoNamesDoNotDependOnTheRegistrationOrder(): void
	{
		$full   = self::router();
		$router = self::router($full->compileTable());

		// The other provider registered first.
		$router->find('GET', '/items/42');

		$get  = $router->find('GET', '/anon')->foundRoute();
		$post = $router->find('POST', '/anon')->foundRoute();

		self::assertSame($full->find('GET', '/anon')->foundRoute()->key(), $get->key());
		self::assertSame($full->find('POST', '/anon')->foundRoute()->key(), $post->key());
		self::assertStringStartsWith('route_', $get->getName());
		self::assertNotSame($get->getName(), $post->getName());
		self::assertFalse($get->getOptions()->isNameExplicit());
	}

	public function testIdenticalDefinitionsGetDistinctAutoNames(): void
	{
		$router  = new Router();
		$handler = static fn () => null;

		$a = $router->get('/twice', $handler);
		$b = $router->get('/twice', $handler);

		self::assertSame($a->getName() . '_2', $b->getName());
	}

	public function testASavedTableLoads(): void
	{
		$table = self::router()->compileTable();
		$dir   = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'oz-route-table-' . \bin2hex(\random_bytes(4));
		$file  = $dir . \DIRECTORY_SEPARATOR . 'api.php';

		try {
			self::assertTrue($table->save($file));
			self::assertSame($table->toArray(), RouteTable::load($file)?->toArray());

			\file_put_contents($file, '<?php return ["format" => 0];');
			self::assertNull(RouteTable::load($file));
		} finally {
			@\unlink($file);
			@\rmdir($dir);
		}

		self::assertNull(RouteTable::load($file));
	}

	public function testATableNotDescribingTheRoutesIsDropped(): void
	{
		$data = self::router()->compileTable()->toArray();

		self::resetRegistrations();

		// GET /items/42 now leads to "/refined", another route of the same provider.
		foreach ($data['dynamic'] as $i => [, , $source, $ordinal]) {
			if (TableItemProvider::class === $source && 0 === $ordinal) {
				$data['dynamic'][$i][3] = 3;
			}
		}

		$dir  = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'oz-route-table-' . \bin2hex(\random_bytes(4));
		$file = $dir . \DIRECTORY_SEPARATOR . 'api.php';

		try {
			self::assertTrue((new RouteTable($data))->save($file));

			$router = self::router(RouteTable::load($file));

			self::assertSame('item.get', $router->find('GET', '/items/42')->foundRoute()->getName());
			self::assertFileDoesNotExist($file);
			// Routed the way a router without a table does.
			self::assertSame([1, 1, 1], self::registrations());
		} finally {
			@\unlink($file);
			@\rmdir($dir);
		}
	}

	/**
	 * A router registering the stub providers in a root group, then applying the refiners as
	 * {@see RouterCreated} does.
	 */
	private static function router(?RouteTable $table = null): Router
	{
		$router = new Router();

		$router->group('/', static function (Router $router) use ($table): void {
			$router->registerProviders(self::PROVIDERS, $table);
		});

		$router->applyRefiners();

		return $router;
	}

	/**
	 * @return array{0: RouteSearchStatus, 1: null|string, 2: array}
	 */
	private static function describe(RouteSearchResult $result): array
	{
		if (RouteSearchStatus::FOUND !== $result->status()) {
			return [$result->status(), null, []];
		}

		return [$result->status(), $result->foundRoute()->key(), $result->foundRouteParams()];
	}

	/**
	 * @param Route[] $routes
	 *
	 * @return string[]
	 */
	private static function keys(array $routes): array
	{
		$keys = \array_map(static fn (Route $route): string => $route->key(), $routes);

		\sort($keys);

		return $keys;
	}

	/**
	 * @return int[]
	 */
	private static function registrations(): array
	{
		return [
			TableItemsProvider::$registrations,
			TableItemProvider::$registrations,
			TableLangProvider::$registrations,
		];
	}

	private static function resetRegistrations(): void
	{
		TableItemsProvider::$registrations = 0;
		TableItemProvider::$registrations  = 0;
		TableLangProvider::$registrations  = 0;
	}
}
