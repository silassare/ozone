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
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\Interfaces\RouteInterceptorInterface;
use OZONE\Core\Router\RouteFormDiscoveryInterceptor;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Tests\TestUtils;
use PHPUnit\Framework\TestCase;

/**
 * Class RouteInterceptorTest.
 *
 * Tests for {@see RouteInterceptorInterface}, {@see RouteFormDiscoveryInterceptor},
 * and the interceptor pipeline in {@see RouteInfo}.
 *
 * @internal
 *
 * @coversNothing
 */
final class RouteInterceptorTest extends TestCase
{
	// -----------------------------------------------------------------------
	// RouteFormDiscoveryInterceptor static contract
	// -----------------------------------------------------------------------

	public function testGetNameReturnsExpectedValue(): void
	{
		self::assertSame('route-form-discovery', RouteFormDiscoveryInterceptor::getName());
	}

	public function testGetPriorityReturnsZero(): void
	{
		self::assertSame(0, RouteFormDiscoveryInterceptor::getPriority());
	}

	public function testInstanceReturnsNewInstanceBoundToRouteInfo(): void
	{
		$router = TestUtils::router();
		$route  = $router->getRoute('foo');
		$ri     = new RouteInfo(context(), $route, []);

		$interceptor = RouteFormDiscoveryInterceptor::instance($ri);

		self::assertInstanceOf(RouteFormDiscoveryInterceptor::class, $interceptor);
	}

	// -----------------------------------------------------------------------
	// shouldIntercept()
	// -----------------------------------------------------------------------

	public function testShouldInterceptReturnsFalseForRegularRequest(): void
	{
		$router = TestUtils::router();
		$route  = $router->getRoute('foo');
		$ri     = new RouteInfo(context(), $route, []);

		$interceptor = RouteFormDiscoveryInterceptor::instance($ri);
		self::assertFalse($interceptor->shouldIntercept());
	}

	public function testShouldInterceptReturnsTrueForDiscoveryRequest(): void
	{
		$router  = TestUtils::router();
		$route   = $router->getRoute('foo');
		$context = $this->makeDiscoveryContext();
		$ri      = new RouteInfo($context, $route, []);

		$interceptor = RouteFormDiscoveryInterceptor::instance($ri);
		self::assertTrue($interceptor->shouldIntercept());
	}

	// -----------------------------------------------------------------------
	// RouteInfo::isIntercepted() / getInterceptor()
	// -----------------------------------------------------------------------

	public function testRouteInfoIsNotInterceptedOnRegularRequest(): void
	{
		$router = TestUtils::router();
		$route  = $router->getRoute('foo');
		$ri     = new RouteInfo(context(), $route, []);

		self::assertFalse($ri->isIntercepted());
		self::assertNull($ri->getInterceptor());
	}

	public function testRouteInfoIsInterceptedOnDiscoveryRequest(): void
	{
		$router  = TestUtils::router();
		$route   = $router->getRoute('foo');
		$context = $this->makeDiscoveryContext();
		$ri      = new RouteInfo($context, $route, []);

		self::assertTrue($ri->isIntercepted());
		self::assertInstanceOf(RouteFormDiscoveryInterceptor::class, $ri->getInterceptor());
	}

	// -----------------------------------------------------------------------
	// RouteInfo::getCleanFormData()
	// -----------------------------------------------------------------------

	public function testGetCleanFormDataReturnsEmptyFormDataWhenIntercepted(): void
	{
		$router  = TestUtils::router();
		$route   = $router->getRoute('foo');
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
		$route  = $router->getRoute('foo');
		$ri     = new RouteInfo(context(), $route, []);

		$ri->getCleanFormData();
	}

	// -----------------------------------------------------------------------
	// RouteInfo::getEffectiveHandler()
	// -----------------------------------------------------------------------

	public function testGetEffectiveHandlerReturnsOriginalHandlerWhenNotIntercepted(): void
	{
		$router = TestUtils::router();
		$route  = $router->getRoute('foo');
		$ri     = new RouteInfo(context(), $route, []);

		self::assertSame($route->getHandler(), $ri->getEffectiveHandler());
	}

	public function testGetEffectiveHandlerReturnsInterceptorHandlerWhenIntercepted(): void
	{
		$router  = TestUtils::router();
		$route   = $router->getRoute('foo');
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

		$route   = $router->getRoute('priority.test');
		$context = $this->makeDiscoveryContext();
		$ri      = new RouteInfo($context, $route, []);

		// The intercepted instance is the first one that accepts — priority 10 runs first.
		self::assertInstanceOf(StubHighPriorityInterceptor::class, $ri->getInterceptor());
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

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
		return true;
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
		return true;
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
