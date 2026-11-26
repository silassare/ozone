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
use OZONE\Core\App\Context;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Router\Route;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Router\RouteSearchResult;
use OZONE\Core\Router\RouteSearchStatus;
use OZONE\Core\Router\RouteSharedOptions;
use OZONE\Core\Router\RouteTable;
use OZONE\Tests\Router\Stubs\TableHostProvider;
use OZONE\Tests\TestUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Routes restricted to a host with {@see RouteSharedOptions::host()}.
 *
 * @internal
 */
#[CoversClass(Router::class)]
#[CoversClass(Route::class)]
#[CoversClass(RouteSharedOptions::class)]
#[CoversClass(RouteTable::class)]
#[CoversClass(Context::class)]
final class RouteHostTest extends TestCase
{
	public function testAHostParamIsReadAsARouteParam(): void
	{
		$router = self::tenantRouter();

		$result = $router->find('GET', '/projects/42', host: 'acme.example.com');

		self::assertSame(RouteSearchStatus::FOUND, $result->status());
		self::assertSame('tenant.project', $result->foundRoute()->getName());
		self::assertSame('42', $result->foundRouteParams()['id'] ?? null);
		self::assertSame('acme', $result->foundRouteParams()['tenant'] ?? null);

		// A static path on a host template.
		$result = $router->find('GET', '/dashboard', host: 'acme.example.com');
		$ri     = new RouteInfo(self::context('acme.example.com'), $result->foundRoute(), $result->foundRouteParams());

		self::assertSame('acme', $ri->param('tenant'));
	}

	public function testALiteralHostMatchesThatHostOnly(): void
	{
		$router = new Router();

		$router->get('/admin', static fn() => null)->name('admin')->host('admin.example.com');

		self::assertSame('admin', self::found($router, 'GET', '/admin', 'admin.example.com'));
		// Hosts compare case-insensitively.
		self::assertSame('admin', self::found($router, 'GET', '/admin', 'Admin.Example.COM'));
		self::assertSame([], $router->find('GET', '/admin', host: 'admin.example.com')->foundRouteParams());

		self::assertNull(self::found($router, 'GET', '/admin', 'other.example.com'));
		self::assertNull(self::found($router, 'GET', '/admin', 'admin.example.com.evil.test'));
		self::assertNull(self::found($router, 'GET', '/admin'));
	}

	public function testARouteWithNoHostMatchesAnyHost(): void
	{
		$router = new Router();

		$router->get('/about', static fn() => null)->name('about');

		self::assertSame('about', self::found($router, 'GET', '/about', 'example.com'));
		self::assertSame('about', self::found($router, 'GET', '/about', 'acme.example.com'));
		self::assertSame('about', self::found($router, 'GET', '/about'));
	}

	public function testARouteOfAHostBeatsARouteOfEveryHostOnThatHostOnly(): void
	{
		$router = self::tenantRouter();

		// The routes of every host were mapped first.
		self::assertSame('tenant.dashboard', self::found($router, 'GET', '/dashboard', 'acme.example.com'));
		self::assertSame('tenant.project', self::found($router, 'GET', '/projects/42', 'acme.example.com'));

		self::assertSame('any.dashboard', self::found($router, 'GET', '/dashboard', 'example.com'));
		self::assertSame('any.project', self::found($router, 'GET', '/projects/42', 'example.com'));
		self::assertSame('any.dashboard', self::found($router, 'GET', '/dashboard'));
	}

	public function testALiteralHostBeatsAHostWithAPartOnTheHostBothMatch(): void
	{
		$router = new Router();

		// The host with a part was mapped first, and still loses where the literal one matches.
		$router->get('/', static fn() => null)->name('tenant')->host('{tenant}.example.com');
		$router->get('/', static fn() => null)->name('www')->host('www.example.com');
		$router->get('/projects/:id', static fn() => null)->name('tenant.project')->host('{tenant}.example.com');
		$router->get('/projects/:id', static fn() => null)->name('www.project')->host('www.example.com');

		self::assertSame('www', self::found($router, 'GET', '/', 'www.example.com'));
		self::assertSame('www.project', self::found($router, 'GET', '/projects/7', 'www.example.com'));
		self::assertSame('tenant', self::found($router, 'GET', '/', 'acme.example.com'));
		self::assertSame('tenant.project', self::found($router, 'GET', '/projects/7', 'acme.example.com'));
	}

	public function testAHigherPriorityStillComesBeforeAHost(): void
	{
		$router = new Router();

		$router->get('/dashboard', static fn() => null)->name('any')->priority(1);
		$router->get('/dashboard', static fn() => null)->name('tenant')->host('{tenant}.example.com');

		self::assertSame('any', self::found($router, 'GET', '/dashboard', 'acme.example.com'));
	}

	public function testAMethodIsNotAllowedOnlyOnTheHostOfItsRoute(): void
	{
		$router = self::tenantRouter();

		// POST /projects exists on a tenant host only.
		self::assertSame(
			RouteSearchStatus::METHOD_NOT_ALLOWED,
			$router->find('GET', '/projects', host: 'acme.example.com')->status()
		);
		self::assertSame(
			RouteSearchStatus::NOT_FOUND,
			$router->find('GET', '/projects', host: 'example.com')->status()
		);
	}

	public function testAHostParamWithNoPatternDoesNotCrossADot(): void
	{
		$router = new Router();

		$router->get('/home', static fn() => null)->name('home')->host('{tenant}.example.com');

		self::assertSame('home', self::found($router, 'GET', '/home', 'acme.example.com'));
		self::assertNull(self::found($router, 'GET', '/home', 'a.b.example.com'));
		self::assertSame('[^.]+', Route::DEFAULT_HOST_PARAM_PATTERN);
	}

	public function testAParamAfterALiteralLabelTakesOneLabelUnlessItsPatternSaysMore(): void
	{
		$router = new Router();
		$h      = static fn() => null;

		$router->get('/v1/ping', $h)->name('one')->host('api.{domain}');
		$router->get('/v2/ping', $h)->name('many')->host('api.{domain}')->param('domain', '[a-z0-9.-]+');

		self::assertSame('one', self::found($router, 'GET', '/v1/ping', 'api.example'));
		self::assertSame(['domain' => 'example'], $router->find('GET', '/v1/ping', host: 'api.example')
			->foundRouteParams());
		self::assertNull(self::found($router, 'GET', '/v1/ping', 'api.example.com'));

		self::assertSame(['domain' => 'example.com'], $router->find('GET', '/v2/ping', host: 'api.example.com')
			->foundRouteParams());
		self::assertSame(['domain' => 'client.co.uk'], $router->find('GET', '/v2/ping', host: 'api.client.co.uk')
			->foundRouteParams());
		self::assertNull(self::found($router, 'GET', '/v2/ping', 'www.example.com'));

		// Built back into an absolute URI on another host, the usual one on the host served.
		$context = self::context('api.example.com', $router);

		self::assertSame(
			'http://api.client.co.uk/v2/ping',
			(string) $context->buildRouteUri('many', ['domain' => 'client.co.uk'])
		);
		self::assertSame('http://api.example.com/v2/ping', (string) $context->buildRouteUri('many'));
		self::assertSame(
			'http://api.example/v1/ping',
			(string) self::context('www.example.com', $router)->buildRouteUri('one', ['domain' => 'example'])
		);
	}

	public function testAnOptionalPartOfAHostIsLeftOutWhenItsParamIsNotGiven(): void
	{
		$router = new Router();

		$router->get('/regional', static fn() => null)
			->name('regional')
			->host('[{region}.]api.{domain}')
			->param('domain', '[a-z0-9.-]+');

		// The host served matched no region: a link from it names none, and stays on it.
		$context = self::context('api.example.com', $router);
		$route   = TestUtils::route($router, 'regional');

		self::assertSame('api.example.com', $route->buildHost($context, ['domain' => 'example.com']));
		self::assertNull($router->buildRouteHost($context, 'regional'));
		self::assertSame('http://api.example.com/regional', (string) $context->buildRouteUri('regional'));

		self::assertSame('eu.api.example.com', $router->buildRouteHost($context, 'regional', ['region' => 'eu']));
		self::assertSame(
			'http://eu.api.example.com/regional',
			(string) $context->buildRouteUri('regional', ['region' => 'eu'])
		);

		// A request reads the region it names, and none when it names none.
		self::assertSame(
			['region' => 'eu', 'domain' => 'example.com'],
			$router->find('GET', '/regional', host: 'eu.api.example.com')->foundRouteParams()
		);
		self::assertSame(
			['domain' => 'example.com'],
			$router->find('GET', '/regional', host: 'api.example.com')->foundRouteParams()
		);
	}

	public function testAHostParamMayNotTakeTheNameOfAPathParam(): void
	{
		$router = new Router();

		$router->get('/users/:tenant', static fn() => null)->name('user')->host('{tenant}.example.com');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageIsOrContains(
			'Route parameter name "tenant" is taken by both the host "{tenant}.example.com"'
				. ' and the path "/users/:tenant".'
		);

		$router->find('GET', '/users/1', host: 'acme.example.com');
	}

	#[DataProvider('provideAHostThatIsNotAHostNameIsRefusedCases')]
	public function testAHostThatIsNotAHostNameIsRefused(string $host): void
	{
		$router = new Router();
		$route  = $router->get('/x', static fn() => null);

		$this->expectException(InvalidArgumentException::class);

		$route->host($host);
	}

	public static function provideAHostThatIsNotAHostNameIsRefusedCases(): iterable
	{
		return [
			'empty'       => [''],
			'with scheme' => ['https://example.com'],
			'with path'   => ['example.com/app'],
			'with port'   => ['example.com:8080'],
		];
	}

	public function testTheHostIsInheritedAndTheInnermostWins(): void
	{
		$router = new Router();
		$h      = static fn() => null;

		$router->group('/outer', static function (Router $router) use ($h): void {
			$router->get('/a', $h)->name('a');
			$router->get('/own', $h)->name('own')->host('own.example.com');

			$router->group('/inner', static function (Router $router) use ($h): void {
				$router->get('/b', $h)->name('b');
			})->name('inner')->host('inner.example.com');

			$router->group('/plain', static function (Router $router) use ($h): void {
				$router->get('/c', $h)->name('c');
			})->name('plain');
		})->name('outer')->host('outer.example.com');

		self::assertSame('outer.example.com', TestUtils::route($router, 'outer.a')->getHost());
		self::assertSame('own.example.com', TestUtils::route($router, 'outer.own')->getHost());
		self::assertSame('inner.example.com', TestUtils::route($router, 'outer.inner.b')->getHost());
		self::assertSame('outer.example.com', TestUtils::route($router, 'outer.plain.c')->getHost());

		self::assertSame('outer.inner.b', self::found($router, 'GET', '/outer/inner/b', 'inner.example.com'));
		self::assertNull(self::found($router, 'GET', '/outer/inner/b', 'outer.example.com'));
		self::assertSame('outer.plain.c', self::found($router, 'GET', '/outer/plain/c', 'outer.example.com'));
	}

	public function testTheTableRoutesByHostAsARouterRegisteringEverything(): void
	{
		$full  = self::providersRouter();
		$table = $full->compileTable();

		$requests = [
			['GET', '/dashboard', 'acme.example.com'],
			['GET', '/dashboard', 'example.com'],
			['GET', '/dashboard', null],
			['GET', '/projects/7', 'acme.example.com'],
			['GET', '/projects/7', 'a.b.example.com'],
			['GET', '/projects', 'acme.example.com'],
			['GET', '/projects', 'example.com'],
			['POST', '/projects', 'acme.example.com'],
			['GET', '/admin', 'admin.example.com'],
			['GET', '/admin', 'ADMIN.example.com'],
			['GET', '/admin', 'acme.example.com'],
			['GET', '/admin', null],
		];

		foreach ($requests as [$method, $path, $host]) {
			self::assertSame(
				self::describe($full->find($method, $path, host: $host)),
				self::describe(self::providersRouter($table)->find($method, $path, host: $host)),
				$method . ' ' . $host . $path
			);
		}

		$router = self::providersRouter($table);

		self::assertSame('tenant.dashboard', self::found($router, 'GET', '/dashboard', 'acme.example.com'));
		self::assertSame('any.dashboard', self::found($router, 'GET', '/dashboard', 'example.com'));
		$params = $router->find('GET', '/projects/7', host: 'acme.example.com')->foundRouteParams();

		self::assertSame('acme', $params['tenant'] ?? null);
	}

	public function testASavedTableKeepsTheHosts(): void
	{
		$table = self::providersRouter()->compileTable();
		$dir   = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'oz-route-table-' . \bin2hex(\random_bytes(4));
		$file  = $dir . \DIRECTORY_SEPARATOR . 'api.php';

		try {
			self::assertTrue($table->save($file));

			$loaded = RouteTable::load($file);

			self::assertNotNull($loaded);
			self::assertSame($table->toArray(), $loaded->toArray());
			self::assertSame(
				[RouteSearchStatus::FOUND, [TableHostProvider::class, 2, false]],
				$loaded->match('GET', '/dashboard', 'acme.example.com')
			);
			self::assertSame(
				[RouteSearchStatus::FOUND, [TableHostProvider::class, 0, false]],
				$loaded->match('GET', '/dashboard', 'example.com')
			);
			self::assertSame([RouteSearchStatus::NOT_FOUND, null], $loaded->match('GET', '/admin', 'example.com'));

			// A table of the layout before hosts is ignored: it would route a host-bound route on every host.
			$data           = $table->toArray();
			$data['format'] = 2;
			\file_put_contents($file, '<?php return ' . \var_export($data, true) . ';');

			self::assertNull(RouteTable::load($file));
		} finally {
			@\unlink($file);
			@\rmdir($dir);
		}
	}

	public function testAUriNamesTheRouteHostWhenItIsNotTheOneServed(): void
	{
		$router  = self::tenantRouter();
		$context = self::context('acme.example.com:8080', $router);

		// Another tenant: absolute, with the scheme and port of the request.
		self::assertSame(
			'http://globex.example.com:8080/projects/7',
			(string) $context->buildRouteUri('tenant.project', ['id' => 7, 'tenant' => 'globex'])
		);
		self::assertSame('globex.example.com', $router->buildRouteHost($context, 'tenant.project', [
			'id'     => 7,
			'tenant' => 'globex',
		]));

		// The same tenant, given or taken from the host served: the usual URI.
		self::assertSame(
			'http://acme.example.com:8080/projects/7',
			(string) $context->buildRouteUri('tenant.project', ['id' => 7, 'tenant' => 'acme'])
		);
		self::assertNull($router->buildRouteHost($context, 'tenant.project', ['id' => 7, 'tenant' => 'acme']));
		self::assertNull($router->buildRouteHost($context, 'tenant.project', ['id' => 7]));
		self::assertSame(
			'http://acme.example.com:8080/dashboard',
			(string) $context->buildRouteUri('tenant.dashboard')
		);

		// A route of every host stays on the host served.
		self::assertNull($router->buildRouteHost($context, 'any.dashboard'));
		self::assertSame('http://acme.example.com:8080/dashboard', (string) $context->buildRouteUri('any.dashboard'));

		// A host param cannot be taken from a host the route does not match.
		$other = self::context('example.com', $router);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageIsOrContains('Missing required parameter value: tenant');

		$other->buildRouteUri('tenant.dashboard');
	}

	#[DataProvider('provideAHostParamValueThatWouldMoveTheUriIsRefusedCases')]
	public function testAHostParamValueThatWouldMoveTheUriIsRefused(string $tenant, string $pattern): void
	{
		$router = new Router();

		$router->get('/home', static fn() => null)
			->name('home')
			->host('{tenant}.example.com')
			->param('tenant', $pattern);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageIsOrContains('is not one it matches');

		$router->buildRouteHost(self::context('example.com', $router), 'home', ['tenant' => $tenant]);
	}

	public static function provideAHostParamValueThatWouldMoveTheUriIsRefusedCases(): iterable
	{
		return [
			'a path'                    => ['intranet/', Route::DEFAULT_HOST_PARAM_PATTERN],
			'user info'                 => ['evil@acme', Route::DEFAULT_HOST_PARAM_PATTERN],
			'a port'                    => ['evil:81', Route::DEFAULT_HOST_PARAM_PATTERN],
			'outside its pattern'       => ['acme_corp', '[a-z0-9-]+'],
			'a dot its pattern refuses' => ['a.b', Route::DEFAULT_HOST_PARAM_PATTERN],
		];
	}

	/**
	 * The routes of {@see TableHostProvider}, mapped in the order it maps them.
	 */
	private static function tenantRouter(): Router
	{
		$router = new Router();

		TableHostProvider::registerRoutes($router);

		return $router;
	}

	/**
	 * A router registering {@see TableHostProvider} as a route provider, through a table if given.
	 */
	private static function providersRouter(?RouteTable $table = null): Router
	{
		$router = new Router();

		$router->group('/', static function (Router $router) use ($table): void {
			$router->registerProviders([TableHostProvider::class => true], $table);
		});

		$router->applyRefiners();

		return $router;
	}

	/**
	 * The name of the route found, null when none is.
	 */
	private static function found(Router $router, string $method, string $path, ?string $host = null): ?string
	{
		$result = $router->find($method, $path, host: $host);

		return RouteSearchStatus::FOUND === $result->status() ? $result->foundRoute()->getName() : null;
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
	 * A request to a host, which URIs are built from with the given router.
	 */
	private static function context(string $host, ?Router $router = null): Context
	{
		$context = new Context(HTTPEnvironment::mock(['HTTP_HOST' => $host]), null, Context::root());

		if (null !== $router) {
			// What OZone::getWebRouter() would answer.
			new ReflectionProperty(Context::class, 't_router')->setValue($context, $router);
		}

		return $context;
	}
}
