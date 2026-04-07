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
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\AbstractResumableFormProvider;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormResumeProgress;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\Enums\RouteFormDocPolicy;
use OZONE\Core\Router\Interfaces\RouteInterceptorInterface;
use OZONE\Core\Router\RouteFormDiscoveryInterceptor;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
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

	// -----------------------------------------------------------------------
	// getFormDeclaration() / form(string)
	// -----------------------------------------------------------------------

	public function testGetFormDeclarationIsNullByDefault(): void
	{
		$router = TestUtils::router();
		$route  = $router->getRoute('foo');

		self::assertNull($route->getOptions()->getFormDeclaration());
	}

	public function testGetFormDeclarationReturnsSetDeclaration(): void
	{
		$router = TestUtils::router();
		$form   = new Form();

		$router->get('/decl-test', static fn () => null)
			->name('decl.test')
			->form($form);

		$route = $router->getRoute('decl.test');

		self::assertNotNull($route->getOptions()->getFormDeclaration());
	}

	public function testFormWithProviderClassStringCreatesDynamicDeclaration(): void
	{
		$router = TestUtils::router();

		$router->post('/provider-test', static fn () => null)
			->name('provider.test')
			->form(StubSharedOptionsProvider::class);

		$route = $router->getRoute('provider.test');
		$decl  = $route->getOptions()->getFormDeclaration();

		self::assertNotNull($decl);
		self::assertSame(RouteFormDocPolicy::DYNAMIC, $decl->getPolicy());
		self::assertSame(StubSharedOptionsProvider::class, $decl->getProviderClass());
	}

	public function testFormWithProviderClassReturnsNullFromGetFormBundle(): void
	{
		$router = TestUtils::router();

		$router->post('/provider-bundle-test', static fn () => null)
			->name('provider.bundle.test')
			->form(StubSharedOptionsProvider::class);

		$route = $router->getRoute('provider.bundle.test');
		$ri    = new RouteInfo(context(), $route, []);

		// Provider declarations bypass the normal bundle path — getFormBundle() returns null.
		self::assertNull($route->getOptions()->getFormBundle($ri));
	}

	// -----------------------------------------------------------------------
	// interceptor() / getInterceptors()
	// -----------------------------------------------------------------------

	public function testGetInterceptorsAlwaysIncludesFormDiscoveryInterceptor(): void
	{
		$router = TestUtils::router();
		$route  = $router->getRoute('foo');
		$name   = RouteFormDiscoveryInterceptor::getName();

		$interceptors = $route->getOptions()->getInterceptors();

		self::assertArrayHasKey($name, $interceptors);
		self::assertSame(RouteFormDiscoveryInterceptor::class, $interceptors[$name]);
	}

	public function testInterceptorMethodAddsCustomInterceptor(): void
	{
		$router = new Router();
		$router->get('/intercept-test', static fn () => null)
			->name('intercept.test')
			->interceptor(StubSharedOptionsInterceptor::class);

		$route        = $router->getRoute('intercept.test');
		$interceptors = $route->getOptions()->getInterceptors();
		$name         = StubSharedOptionsInterceptor::getName();

		self::assertArrayHasKey($name, $interceptors);
		self::assertSame(StubSharedOptionsInterceptor::class, $interceptors[$name]);
	}

	public function testInterceptorMethodThrowsForNonExistentClass(): void
	{
		$this->expectException(RuntimeException::class);

		$router = new Router();
		$router->get('/bad-intercept', static fn () => null)
			->interceptor('OZONE\NonExistent\SomeInterceptor');
	}

	public function testInterceptorMethodThrowsForNonInterceptorClass(): void
	{
		$this->expectException(RuntimeException::class);

		$router = new Router();
		$router->get('/bad-intercept2', static fn () => null)
			->interceptor(StubSharedOptionsProvider::class);
	}

	public function testGetInterceptorsFromParentAreInheritedByChildRoute(): void
	{
		$router = new Router();
		$router->group('/parent', static function (Router $router) {
			$router->get('/child', static fn () => null)
				->name('child');
		})
			->name('parent')
			->interceptor(StubSharedOptionsInterceptor::class);

		$route        = $router->getRoute('parent.child');
		$interceptors = $route->getOptions()->getInterceptors();
		$name         = StubSharedOptionsInterceptor::getName();

		self::assertArrayHasKey($name, $interceptors);
	}

	public function testAddingDuplicateInterceptorByNameKeepsLatestDefinition(): void
	{
		$router = new Router();
		$router->get('/dedup', static fn () => null)
			->name('dedup.test')
			->interceptor(StubSharedOptionsInterceptor::class)
			->interceptor(StubSharedOptionsInterceptor::class);

		$route        = $router->getRoute('dedup.test');
		$interceptors = $route->getOptions()->getInterceptors();
		$name         = StubSharedOptionsInterceptor::getName();

		// Same name — only one entry should be present (last write wins in the map).
		self::assertCount(1, \array_filter($interceptors, static fn ($v) => StubSharedOptionsInterceptor::class === $v));
	}
}

/**
 * Minimal provider stub for use in RouteSharedOptionsTest.
 *
 * @internal
 */
final class StubSharedOptionsProvider extends AbstractResumableFormProvider
{
	public static function getName(): string
	{
		return 'test:shared-options-stub';
	}

	public function nextStep(FormData $cleaned_form, FormResumeProgress $progress): ?Form
	{
		return null;
	}
}

/**
 * Minimal interceptor stub for use in RouteSharedOptionsTest.
 *
 * @internal
 */
final class StubSharedOptionsInterceptor implements RouteInterceptorInterface
{
	public function __construct(private readonly RouteInfo $ri) {}

	#[Override]
	public static function getName(): string
	{
		return 'stub-shared-options-interceptor';
	}

	#[Override]
	public static function getPriority(): int
	{
		return 5;
	}

	#[Override]
	public function shouldIntercept(): bool
	{
		return false;
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
