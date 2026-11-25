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
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\RateLimitReachedException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormDiscoveryRouteInterceptor;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\Interfaces\RouteInterceptorInterface;
use OZONE\Core\Router\Interfaces\RouteRateLimitInterface;
use OZONE\Core\Router\Rates\RateLimit;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Router\RouteRateLimiter;
use OZONE\Tests\TestUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class RouteInterceptorTest.
 *
 * Tests for {@see RouteInterceptorInterface}, {@see FormDiscoveryRouteInterceptor},
 * and the interceptor pipeline in {@see RouteInfo}.
 *
 * @internal
 */
#[CoversClass(FormDiscoveryRouteInterceptor::class)]
#[CoversClass(RouteInfo::class)]
final class RouteInterceptorTest extends TestCase
{
	// -----------------------------------------------------------------------
	// FormDiscoveryRouteInterceptor static contract
	// -----------------------------------------------------------------------

	public function testGetNameReturnsExpectedValue(): void
	{
		self::assertSame('oz:form:discovery:route:interceptor', FormDiscoveryRouteInterceptor::getName());
	}

	public function testGetPriorityReturnsZero(): void
	{
		self::assertSame(0, FormDiscoveryRouteInterceptor::getPriority());
	}

	public function testInstanceReturnsNewInstanceBoundToRouteInfo(): void
	{
		$router = TestUtils::router();
		$route  = TestUtils::route($router, 'foo');
		$ri     = new RouteInfo(context(), $route, []);

		$interceptor = FormDiscoveryRouteInterceptor::instance($ri);

		self::assertInstanceOf(FormDiscoveryRouteInterceptor::class, $interceptor);
	}

	// -----------------------------------------------------------------------
	// shouldIntercept()
	// -----------------------------------------------------------------------

	public function testShouldInterceptReturnsFalseForRegularRequest(): void
	{
		$router = TestUtils::router();
		$route  = TestUtils::route($router, 'foo');
		$ri     = new RouteInfo(context(), $route, []);

		$interceptor = FormDiscoveryRouteInterceptor::instance($ri);
		self::assertFalse($interceptor->shouldIntercept());
	}

	public function testShouldInterceptReturnsTrueForDiscoveryRequest(): void
	{
		$router  = TestUtils::router();
		$route   = TestUtils::route($router, 'foo');
		$context = $this->makeDiscoveryContext();
		$ri      = new RouteInfo($context, $route, []);

		$interceptor = FormDiscoveryRouteInterceptor::instance($ri);
		self::assertTrue($interceptor->shouldIntercept());
	}

	// -----------------------------------------------------------------------
	// RouteInfo::isIntercepted() / getInterceptor()
	// -----------------------------------------------------------------------

	public function testRouteInfoIsNotInterceptedOnRegularRequest(): void
	{
		$router = TestUtils::router();
		$route  = TestUtils::route($router, 'foo');
		$ri     = new RouteInfo(context(), $route, []);

		self::assertFalse($ri->isIntercepted());
		self::assertNull($ri->getInterceptor());
	}

	public function testRouteInfoIsInterceptedOnDiscoveryRequest(): void
	{
		$router  = TestUtils::router();
		$route   = TestUtils::route($router, 'foo');
		$context = $this->makeDiscoveryContext();
		$ri      = new RouteInfo($context, $route, []);

		self::assertTrue($ri->isIntercepted());
		self::assertInstanceOf(FormDiscoveryRouteInterceptor::class, $ri->getInterceptor());
	}

	// -----------------------------------------------------------------------
	// RouteInfo::getCleanFormData()
	// -----------------------------------------------------------------------

	public function testGetCleanFormDataReturnsEmptyFormDataWhenIntercepted(): void
	{
		$router  = TestUtils::router();
		$route   = TestUtils::route($router, 'foo');
		$context = $this->makeDiscoveryContext();
		$ri      = new RouteInfo($context, $route, []);

		$data = $ri->getCleanFormData();
		self::assertInstanceOf(FormData::class, $data);
		self::assertEmpty($data->getData());
	}

	public function testGetCleanFormDataThrowsWhenNotInterceptedAndFormNotChecked(): void
	{
		$this->expectException(RuntimeException::class);

		$router = TestUtils::router();
		$route  = TestUtils::route($router, 'foo');
		$ri     = new RouteInfo(context(), $route, []);

		$ri->getCleanFormData();
	}

	// -----------------------------------------------------------------------
	// RouteInfo::getEffectiveHandler()
	// -----------------------------------------------------------------------

	public function testGetEffectiveHandlerReturnsOriginalHandlerWhenNotIntercepted(): void
	{
		$router = TestUtils::router();
		$route  = TestUtils::route($router, 'foo');
		$ri     = new RouteInfo(context(), $route, []);

		self::assertSame($route->getHandler(), $ri->getEffectiveHandler());
	}

	public function testGetEffectiveHandlerReturnsInterceptorHandlerWhenIntercepted(): void
	{
		$router  = TestUtils::router();
		$route   = TestUtils::route($router, 'foo');
		$context = $this->makeDiscoveryContext();
		$ri      = new RouteInfo($context, $route, []);

		$handler = $ri->getEffectiveHandler();

		self::assertNotSame($route->getHandler(), $handler);
		self::assertIsCallable($handler);
	}

	// -----------------------------------------------------------------------
	// Custom interceptor priority ordering
	// -----------------------------------------------------------------------

	public function testHigherPriorityInterceptorRunsFirst(): void
	{
		$order = [];

		$router = new Router();
		$router->get('/priority-test', static fn () => null)
			->name('priority.test')
			->interceptor(StubHighPriorityInterceptor::class)
			->interceptor(StubLowPriorityInterceptor::class);

		$route   = TestUtils::route($router, 'priority.test');
		$context = $this->makeDiscoveryContext();
		$ri      = new RouteInfo($context, $route, []);

		// The intercepted instance is the first one that accepts — priority 10 runs first.
		self::assertInstanceOf(StubHighPriorityInterceptor::class, $ri->getInterceptor());
	}

	// -----------------------------------------------------------------------
	// Rate limit and interceptors
	// -----------------------------------------------------------------------

	public function testAFormDiscoveryIsNotCountedAgainstTheRouteRateLimit(): void
	{
		$limit  = new RateLimit('test:discovery:' . \uniqid('', true), 2, 60);
		$router = new Router();
		$router->get('/rate-limited', static fn () => null)
			->name('rate.limited')
			->rateLimit($limit);

		$route = TestUtils::route($router, 'rate.limited');

		try {
			// Five discoveries on a limit of two: none is refused, and none spends an attempt.
			for ($i = 0; $i < 5; ++$i) {
				$ri = new RouteInfo($this->makeDiscoveryContext(), $route, []);
				self::assertInstanceOf(FormDiscoveryRouteInterceptor::class, $ri->getInterceptor());
			}

			self::assertSame(2, RouteRateLimiter::get($ri, $limit)->status()['remaining']);

			// Both attempts are still there for the requests that submit, and the third is refused.
			new RouteInfo(context(), $route, []);
			new RouteInfo(context(), $route, []);

			$this->expectException(RateLimitReachedException::class);
			new RouteInfo(context(), $route, []);
		} finally {
			$ri = new RouteInfo(context(), $route, []);

			RouteRateLimiter::get($ri, $limit)->reset();
			RouteRateLimiter::get($ri, self::discoveryLimit($ri))->reset();
		}
	}

	public function testARequestAnsweredByAnotherInterceptorIsCountedEvenWithTheDiscoveryHeader(): void
	{
		$limit  = new RateLimit('test:intercepted:' . \uniqid('', true), 2, 60);
		$router = new Router();
		$router->get('/rate-limited-intercepted', static fn () => null)
			->name('rate.limited.intercepted')
			->interceptor(StubHighPriorityInterceptor::class)
			->rateLimit($limit);

		$route = TestUtils::route($router, 'rate.limited.intercepted');

		try {
			// The discovery header is sent, but a higher-priority interceptor answers: it is counted.
			$ri = new RouteInfo($this->makeDiscoveryContext(), $route, []);
			self::assertInstanceOf(StubHighPriorityInterceptor::class, $ri->getInterceptor());
			new RouteInfo($this->makeDiscoveryContext(), $route, []);

			$this->expectException(RateLimitReachedException::class);
			new RouteInfo($this->makeDiscoveryContext(), $route, []);
		} finally {
			RouteRateLimiter::get(new RouteInfo(context(), $route, []), $limit)->reset();
		}
	}

	public function testAFormDiscoveryIsCountedAgainstALimitOfItsOwn(): void
	{
		Settings::set('oz.forms', 'OZ_FORM_DISCOVERY_IP_RATE', 3);

		$limit  = new RateLimit('test:discovery-own:' . \uniqid('', true), 1, 60);
		$router = new Router();
		$router->get('/rate-limited-discovered', static fn () => null)
			->name('rate.limited.discovered')
			->rateLimit($limit);

		$route = TestUtils::route($router, 'rate.limited.discovered');
		$ri    = new RouteInfo(context(), $route, []);

		try {
			// The route's own single attempt, spent: the discoveries are not refused for it.
			self::assertSame(0, RouteRateLimiter::get($ri, $limit)->status()['remaining']);

			for ($i = 0; $i < 3; ++$i) {
				new RouteInfo($this->makeDiscoveryContext(), $route, []);
			}

			self::assertSame(0, RouteRateLimiter::get($ri, self::discoveryLimit($ri))->status()['remaining']);

			$this->expectException(RateLimitReachedException::class);
			new RouteInfo($this->makeDiscoveryContext(), $route, []);
		} finally {
			Settings::unset('oz.forms', 'OZ_FORM_DISCOVERY_IP_RATE');
			RouteRateLimiter::get($ri, $limit)->reset();
			RouteRateLimiter::get($ri, self::discoveryLimit($ri))->reset();
		}
	}

	public function testADiscoveryIsCountedOnceUnderStackedLimits(): void
	{
		Settings::set('oz.forms', 'OZ_FORM_DISCOVERY_IP_RATE', 3);

		$router = new Router();
		$router->group('/stacked', static function (Router $router): void {
			$router->get('/discovered', static fn () => null)
				->name('rate.limited.stacked')
				->rateLimit(new RateLimit('test:stacked-route:' . \uniqid('', true), 5, 60));
		})->rateLimit(new RateLimit('test:stacked-group:' . \uniqid('', true), 5, 60));

		$route = TestUtils::route($router, 'rate.limited.stacked');

		try {
			// Two limits on the route, three discoveries allowed: each is one of the three, not two.
			for ($i = 0; $i < 3; ++$i) {
				$ri = new RouteInfo($this->makeDiscoveryContext(), $route, []);
			}

			self::assertSame(0, RouteRateLimiter::get($ri, self::discoveryLimit($ri))->status()['remaining']);

			$this->expectException(RateLimitReachedException::class);
			new RouteInfo($this->makeDiscoveryContext(), $route, []);
		} finally {
			Settings::unset('oz.forms', 'OZ_FORM_DISCOVERY_IP_RATE');
			$plain = new RouteInfo(context(), $route, []);

			RouteRateLimiter::get($plain, self::discoveryLimit($plain))->reset();
		}
	}

	public function testNoDiscoveryIsCountedWhenItsRateIsZero(): void
	{
		Settings::set('oz.forms', 'OZ_FORM_DISCOVERY_IP_RATE', 0);

		$limit  = new RateLimit('test:discovery-off:' . \uniqid('', true), 1, 60);
		$router = new Router();
		$router->get('/rate-limited-undiscovered', static fn () => null)
			->name('rate.limited.undiscovered')
			->rateLimit($limit);

		$route = TestUtils::route($router, 'rate.limited.undiscovered');
		$plain = new RouteInfo(context(), $route, []);

		try {
			self::assertNull(FormDiscoveryRouteInterceptor::rateLimit($plain));

			for ($i = 0; $i < 5; ++$i) {
				$ri = new RouteInfo($this->makeDiscoveryContext(), $route, []);
				self::assertInstanceOf(FormDiscoveryRouteInterceptor::class, $ri->getInterceptor());
			}
		} finally {
			Settings::unset('oz.forms', 'OZ_FORM_DISCOVERY_IP_RATE');
			RouteRateLimiter::get($plain, $limit)->reset();
		}
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * The limit the route's discoveries are counted against.
	 */
	private static function discoveryLimit(RouteInfo $ri): RouteRateLimitInterface
	{
		$limit = FormDiscoveryRouteInterceptor::rateLimit($ri);

		self::assertNotNull($limit);

		return $limit;
	}

	private function makeDiscoveryContext(): Context
	{
		$headerName = Settings::get('oz.request', 'OZ_FORM_DISCOVERY_HEADER_NAME');
		$serverKey  = 'HTTP_' . \strtoupper(\str_replace('-', '_', $headerName));

		return new Context(HTTPEnvironment::mock([$serverKey => '?1']), null, Context::root());
	}
}

// -----------------------------------------------------------------------
// Stub interceptors used only by RouteInterceptorTest
// -----------------------------------------------------------------------

/**
 * @internal
 */
final class StubHighPriorityInterceptor implements RouteInterceptorInterface
{
	public function __construct(private readonly RouteInfo $ri) {}

	#[Override]
	public static function getName(): string
	{
		return 'stub-high-priority';
	}

	#[Override]
	public static function getPriority(): int
	{
		return 10;
	}

	#[Override]
	public function shouldIntercept(): bool
	{
		// Answered from what it was built with, as an interceptor decides: a stub that ignored its
		// RouteInfo would not be one.
		return $this->ri instanceof RouteInfo;
	}

	#[Override]
	public function handle(): Response
	{
		return new Response();
	}

	#[Override]
	public static function instance(RouteInfo $ri): static
	{
		return new self($ri);
	}
}

/**
 * @internal
 */
final class StubLowPriorityInterceptor implements RouteInterceptorInterface
{
	public function __construct(private readonly RouteInfo $ri) {}

	#[Override]
	public static function getName(): string
	{
		return 'stub-low-priority';
	}

	#[Override]
	public static function getPriority(): int
	{
		return 1;
	}

	#[Override]
	public function shouldIntercept(): bool
	{
		// Answered from what it was built with, as an interceptor decides: a stub that ignored its
		// RouteInfo would not be one.
		return $this->ri instanceof RouteInfo;
	}

	#[Override]
	public function handle(): Response
	{
		return new Response();
	}

	#[Override]
	public static function instance(RouteInfo $ri): static
	{
		return new self($ri);
	}
}
