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

use Override;
use OZONE\Core\Access\AccessRights;
use OZONE\Core\Access\Interfaces\AccessRightsInterface;
use OZONE\Core\App\Context;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodStatefulInterface;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\StatefulAuthenticationMethodStore;
use OZONE\Core\Exceptions\RateLimitReachedException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Router\Interfaces\RouteRateLimitInterface;
use OZONE\Core\Router\Rates\IPRateLimit;
use OZONE\Core\Router\Rates\RateLimit;
use OZONE\Core\Router\Rates\StatefulAuthRateLimit;
use OZONE\Core\Router\Rates\UserRateLimit;
use OZONE\Core\Router\Route;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Router\RouteRateLimiter;
use OZONE\Core\Router\RouteSharedOptions;
use OZONE\Tests\Support\TestAuthUser;
use OZONE\Tests\TestUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class RouteRateLimitTest.
 *
 * What a route's rate limits count: a request once per key whatever the number of limits a route
 * stacks on it, each limit holding that count to its own rate; and a quota per route for every kind
 * of limit, by address, by user or by session.
 *
 * @internal
 */
#[CoversClass(RouteSharedOptions::class)]
#[CoversClass(RouteRateLimiter::class)]
#[CoversClass(RateLimit::class)]
#[CoversClass(IPRateLimit::class)]
#[CoversClass(UserRateLimit::class)]
#[CoversClass(StatefulAuthRateLimit::class)]
final class RouteRateLimitTest extends TestCase
{
	/** @var list<array{0: RouteInfo, 1: RouteRateLimitInterface}> what each test counted, reset after it */
	private array $counted = [];

	#[Override]
	protected function tearDown(): void
	{
		foreach ($this->counted as [$ri, $limit]) {
			RouteRateLimiter::get($ri, $limit)->reset();
		}

		$this->counted                = [];
		RouteRateLimitTestAuth::$user = null;

		parent::tearDown();
	}

	public function testStackedLimitsOnOneKeyCountARequestOnceAndTheStrictestGoverns(): void
	{
		// A loose limit on the group, a strict one on the route, both by address: one key.
		$route = self::stacked('stacked.strict.route', 100, 2);
		$ri    = $this->dispatch($route);

		self::assertSame(1, RouteRateLimiter::get($ri, new IPRateLimit($ri, 2, 60))->status()['remaining']);

		$this->dispatch($route);

		$this->expectException(RateLimitReachedException::class);
		$this->dispatch($route);
	}

	public function testTheStrictestGovernsWhicheverLevelDeclaresIt(): void
	{
		$route = self::stacked('stacked.strict.group', 2, 100);

		$this->dispatch($route);
		$this->dispatch($route);

		$this->expectException(RateLimitReachedException::class);
		$this->dispatch($route);
	}

	public function testAStrictLimitWithALongWindowHoldsUnderALooserOneWithAShortWindow(): void
	{
		// The group: loose, in a window of one second. The route: two requests a minute. One key.
		$router = new Router();
		$router->group('/windows', static function (Router $router): void {
			$router->get('/x', static fn () => null)
				->name('stacked.windows')
				->rateLimit(static fn (RouteInfo $ri) => new IPRateLimit($ri, 2, 60));
		})->rateLimit(static fn (RouteInfo $ri) => new IPRateLimit($ri, 100, 1));

		$route = TestUtils::route($router, 'stacked.windows');
		$ri    = $this->dispatch($route);

		$this->counted[] = [$ri, new IPRateLimit($ri, 2, 60)];
		$this->counted[] = [$ri, new IPRateLimit($ri, 100, 1)];

		$this->dispatch($route);

		// The short window ends; the minute has not.
		\usleep(2_100_000);

		$this->expectException(RateLimitReachedException::class);
		$this->dispatch($route);
	}

	public function testStackedLimitsOnDifferentKeysCountApart(): void
	{
		$group  = new RateLimit('test:apart-group:' . \uniqid('', true), 5, 60);
		$own    = new RateLimit('test:apart-route:' . \uniqid('', true), 5, 60);
		$router = new Router();
		$router->group('/apart', static function (Router $router) use ($own): void {
			$router->get('/x', static fn () => null)->name('stacked.apart')->rateLimit($own);
		})->rateLimit($group);

		$ri = $this->dispatch(TestUtils::route($router, 'stacked.apart'));

		$this->counted[] = [$ri, $group];
		$this->counted[] = [$ri, $own];

		self::assertSame(4, RouteRateLimiter::get($ri, $group)->status()['remaining']);
		self::assertSame(4, RouteRateLimiter::get($ri, $own)->status()['remaining']);
	}

	public function testAUserHasAQuotaOnEachRoute(): void
	{
		RouteRateLimitTestAuth::$user = new TestAuthUser('user', '4242');

		$router = new Router();

		foreach (['a', 'b'] as $name) {
			$router->get('/by-user/' . $name, static fn () => null)
				->name('by.user.' . $name)
				->withAuthentication(RouteRateLimitTestAuth::class)
				->rateLimit(static fn (RouteInfo $ri) => new UserRateLimit($ri, 1, 60));
		}

		$a = $this->dispatch(TestUtils::route($router, 'by.user.a'), true);
		$b = $this->dispatch(TestUtils::route($router, 'by.user.b'), true);

		$this->counted[] = [$a, new UserRateLimit($a, 1, 60)];
		$this->counted[] = [$b, new UserRateLimit($b, 1, 60)];

		self::assertSame(
			'user:' . TestUtils::route($router, 'by.user.a')->key() . ':user.4242',
			new UserRateLimit($a, 1, 60)->key()
		);

		// Route b's one request was its own, not the second of a quota shared with route a.
		$this->expectException(RateLimitReachedException::class);
		$this->dispatch(TestUtils::route($router, 'by.user.a'), true);
	}

	public function testASessionHasAQuotaOnEachRoute(): void
	{
		RouteRateLimitTestAuth::$user = new TestAuthUser('user', '4243');

		$router = new Router();

		foreach (['a', 'b'] as $name) {
			$router->get('/by-session/' . $name, static fn () => null)
				->name('by.session.' . $name)
				->withAuthentication(RouteRateLimitTestAuth::class)
				->rateLimit(static fn (RouteInfo $ri) => new StatefulAuthRateLimit($ri, 1, 60));
		}

		$a = $this->dispatch(TestUtils::route($router, 'by.session.a'), true);
		$b = $this->dispatch(TestUtils::route($router, 'by.session.b'), true);

		$this->counted[] = [$a, new StatefulAuthRateLimit($a, 1, 60)];
		$this->counted[] = [$b, new StatefulAuthRateLimit($b, 1, 60)];

		self::assertSame(
			'session:' . TestUtils::route($router, 'by.session.a')->key() . ':' . RouteRateLimitTestAuth::STATE_ID,
			new StatefulAuthRateLimit($a, 1, 60)->key()
		);

		$this->expectException(RateLimitReachedException::class);
		$this->dispatch(TestUtils::route($router, 'by.session.a'), true);
	}

	/**
	 * A route limited by address in its group and on itself, each at its own rate.
	 */
	private static function stacked(string $name, int $group_rate, int $route_rate): Route
	{
		$router = new Router();
		$router->group('/' . \str_replace('.', '-', $name), static function (Router $router) use ($name, $route_rate): void {
			$router->get('/x', static fn () => null)
				->name($name)
				->rateLimit(static fn (RouteInfo $ri) => new IPRateLimit($ri, $route_rate, 60));
		})->rateLimit(static fn (RouteInfo $ri) => new IPRateLimit($ri, $group_rate, 60));

		return TestUtils::route($router, $name);
	}

	/**
	 * A request to a route, its middlewares run, signed in by its authentication when asked.
	 */
	private function dispatch(Route $route, bool $authenticated = false): RouteInfo
	{
		$context = new Context(HTTPEnvironment::mock(), null, Context::root());
		$ri      = new RouteInfo(
			$context,
			$route,
			[],
			$authenticated ? static function (RouteInfo $ri) use ($context): void {
				$context->authState()->authenticate($ri);
			} : null
		);

		$this->counted[] = [$ri, new IPRateLimit($ri, 1, 60)];

		return $ri;
	}
}

/**
 * A stateful authentication method of the routes under test: the user a test sets, in one session.
 *
 * @internal
 */
final class RouteRateLimitTestAuth implements AuthenticationMethodStatefulInterface
{
	public const string STATE_ID = 'rate-limit-test-session';

	public static ?AuthUserInterface $user = null;

	#[Override]
	public static function get(RouteInfo $ri, string $realm): static
	{
		return new self();
	}

	#[Override]
	public function satisfied(): bool
	{
		return true;
	}

	#[Override]
	public function authenticate(): void {}

	#[Override]
	public function user(): AuthUserInterface
	{
		return self::$user ?? throw new RuntimeException('No user set.');
	}

	#[Override]
	public function getAccessRights(): AccessRightsInterface
	{
		return new AccessRights();
	}

	#[Override]
	public function isScoped(): bool
	{
		return false;
	}

	#[Override]
	public function ask(): void {}

	#[Override]
	public function stateID(): string
	{
		return self::STATE_ID;
	}

	#[Override]
	public function store(): StatefulAuthenticationMethodStore
	{
		throw new RuntimeException('No store in this test.');
	}

	#[Override]
	public function persist(): void {}

	#[Override]
	public function destroy(): void {}

	#[Override]
	public function renew(): void {}

	#[Override]
	public function attachAuthUser(AuthUserInterface $user): void {}

	#[Override]
	public function detachAuthUser(): void {}

	#[Override]
	public function lifetime(): int
	{
		return 60;
	}
}
