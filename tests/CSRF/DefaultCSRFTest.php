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

namespace OZONE\Tests\CSRF;

use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodInterface;
use OZONE\Core\Auth\Methods\BearerAuth;
use OZONE\Core\Auth\Methods\SessionAuth;
use OZONE\Core\CSRF\CSRF;
use OZONE\Core\Http\Enums\RequestScope;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Router\RouteGroup;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Router\RouteSharedOptions;
use OZONE\Core\Sessions\Session;
use PHPUnit\Framework\TestCase;

/**
 * Class DefaultCSRFTest.
 *
 * Tests for {@see CSRF::isRequiredByDefault()}.
 *
 * @internal
 *
 * @covers \OZONE\Core\CSRF\CSRF
 */
final class DefaultCSRFTest extends TestCase
{
	private static Router $router;

	/**
	 * @var array<string, RouteSharedOptions>
	 */
	private static array $options = [];

	public static function setUpBeforeClass(): void
	{
		self::$router = new Router();
		$noop         = static fn () => null;

		self::$router->get('/plain', $noop)->name('csrf:plain');

		self::$options['default']  = self::$router->post('/default', $noop)->name('csrf:default');
		self::$options['out']      = self::$router->post('/out', $noop)->name('csrf:out')->withoutCSRF();
		self::$options['explicit'] = self::$router->post('/explicit', $noop)->withCSRF(RequestScope::HOST);

		self::$router->group('/hooks', static function (Router $router, RouteGroup $group) use ($noop): void {
			$group->withoutCSRF();
			self::$options['group']     = $router->post('/in', $noop);
			self::$options['opt-in']    = $router->post('/opt-in', $noop)->withCSRF(RequestScope::STATE);
		});
	}

	protected function tearDown(): void
	{
		Settings::unset('oz.request', 'OZ_CSRF_SESSION_DEFAULT');
	}

	public function testUnsafeSessionRequestWithTheSessionCookieNeedsAToken(): void
	{
		self::assertTrue(self::required('default'));
		self::assertTrue(self::required('default', 'DELETE'));
	}

	public function testSafeMethodsAreNotChecked(): void
	{
		self::assertFalse(self::required('default', 'GET'));
		self::assertFalse(self::required('default', 'HEAD'));
		self::assertFalse(self::required('default', 'OPTIONS'));
	}

	public function testRequestsWithoutTheSessionCookieAreNotChecked(): void
	{
		self::assertFalse(self::required('default', 'POST', false));
	}

	public function testOtherAuthMethodsAreNotChecked(): void
	{
		self::assertFalse(self::required('default', 'POST', true, BearerAuth::class));
		self::assertFalse(self::required('default', 'POST', true, null));
	}

	public function testRouteAndGroupCanOptOut(): void
	{
		self::assertFalse(self::required('out'));
		self::assertFalse(self::required('group'));
	}

	public function testAnExplicitCheckReplacesTheDefault(): void
	{
		self::assertFalse(self::required('explicit'));
		self::assertFalse(self::required('opt-in'));
		self::assertSame(RequestScope::STATE, self::$options['opt-in']->getCSRFScope());
	}

	public function testTheDefaultCanBeTurnedOff(): void
	{
		Settings::set('oz.request', 'OZ_CSRF_SESSION_DEFAULT', false);

		self::assertFalse(self::required('default'));
	}

	/**
	 * @param null|class-string<AuthenticationMethodInterface> $auth
	 */
	private static function required(
		string $route,
		string $method = 'POST',
		bool $with_cookie = true,
		?string $auth = SessionAuth::class
	): bool {
		$env = ['REQUEST_METHOD' => $method];

		if ($with_cookie) {
			$env['HTTP_COOKIE'] = Session::cookieName() . '=' . \str_repeat('a', 64);
		}

		$context = new Context(HTTPEnvironment::mock($env), null, Context::root());
		$method  = null;

		if (null !== $auth) {
			$ri     = new RouteInfo($context, self::$router->getRoute('csrf:plain'), []);
			$method = $auth::get($ri, 'test');
		}

		return CSRF::isRequiredByDefault(self::$options[$route], $context, $method);
	}
}
