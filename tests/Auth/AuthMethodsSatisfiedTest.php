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

namespace OZONE\Tests\Auth;

use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Methods\ApiKeyHeaderAuth;
use OZONE\Core\Auth\Methods\BearerAuth;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Router\Route;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use PHPUnit\Framework\TestCase;

/**
 * Tests the satisfied() method on bearer and API-key authentication methods.
 *
 * Both BearerAuth and ApiKeyHeaderAuth examine the incoming request headers to
 * decide whether they "see" their credential.  These tests verify the correct
 * return value for each header scenario.
 *
 * Regression note: before the fix, ApiKeyHeaderAuth::satisfied() always returned
 * false even when the API-key header was present, making API-key authentication
 * completely non-functional.
 *
 * @internal
 *
 * @coversNothing
 */
final class AuthMethodsSatisfiedTest extends TestCase
{
	/** Minimal router/route used to construct RouteInfo in every test. */
	private static Router $router;
	private static Route $route;

	public static function setUpBeforeClass(): void
	{
		self::$router = new Router();
		self::$router->get('/test-auth-method', static fn () => null)->name('test:auth.method');
		self::$route  = self::$router->getRoute('test:auth.method');
	}

	// -----------------------------------------------------------------------
	// BearerAuth::satisfied()
	// -----------------------------------------------------------------------

	public function testBearerSatisfiedReturnsFalseWhenNoAuthorizationHeader(): void
	{
		$ri = $this->makeRouteInfo(HTTPEnvironment::mock());
		self::assertFalse(BearerAuth::get($ri, 'test')->satisfied());
	}

	public function testBearerSatisfiedReturnsFalseForBasicScheme(): void
	{
		$ri = $this->makeRouteInfo(HTTPEnvironment::mock([
			'HTTP_AUTHORIZATION' => 'Basic dXNlcjpwYXNz',
		]));
		self::assertFalse(BearerAuth::get($ri, 'test')->satisfied());
	}

	public function testBearerSatisfiedReturnsFalseForEmptyToken(): void
	{
		// The header line starts with "Bearer " but has nothing after it.
		$ri = $this->makeRouteInfo(HTTPEnvironment::mock([
			'HTTP_AUTHORIZATION' => 'Bearer ',
		]));
		self::assertFalse(BearerAuth::get($ri, 'test')->satisfied());
	}

	public function testBearerSatisfiedReturnsTrueForValidBearerHeader(): void
	{
		$token = 'a1b2c3d4e5f6';
		$ri    = $this->makeRouteInfo(HTTPEnvironment::mock([
			'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
		]));

		$method = BearerAuth::get($ri, 'test');
		self::assertTrue($method->satisfied());
		self::assertSame($token, $method->getToken());
	}

	public function testBearerSatisfiedIsCaseInsensitiveOnScheme(): void
	{
		$ri = $this->makeRouteInfo(HTTPEnvironment::mock([
			'HTTP_AUTHORIZATION' => 'BEARER sometoken',
		]));
		self::assertTrue(BearerAuth::get($ri, 'test')->satisfied());
	}

	// -----------------------------------------------------------------------
	// ApiKeyHeaderAuth::satisfied()
	// -----------------------------------------------------------------------

	/**
	 * Regression: before the fix, this returned false even when the header was
	 * present, rendering API-key authentication completely non-functional.
	 */
	public function testApiKeyHeaderSatisfiedReturnsTrueWhenHeaderIsPresent(): void
	{
		$api_key = 'my-super-secret-api-key';
		$ri      = $this->makeRouteInfo($this->envWithApiKey($api_key));

		$method = ApiKeyHeaderAuth::get($ri, 'test');
		self::assertTrue($method->satisfied());
		self::assertSame($api_key, $method->getApiKey());
	}

	public function testApiKeyHeaderSatisfiedReturnsFalseWhenHeaderIsMissing(): void
	{
		$ri = $this->makeRouteInfo(HTTPEnvironment::mock());
		self::assertFalse(ApiKeyHeaderAuth::get($ri, 'test')->satisfied());
	}

	public function testApiKeyHeaderSatisfiedReturnsFalseForEmptyHeaderValue(): void
	{
		$key         = Settings::get('oz.auth', 'OZ_AUTH_API_KEY_HEADER_NAME');
		$server_key  = 'HTTP_' . \strtoupper(\str_replace('-', '_', $key));
		$ri          = $this->makeRouteInfo(HTTPEnvironment::mock([$server_key => '']));
		self::assertFalse(ApiKeyHeaderAuth::get($ri, 'test')->satisfied());
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/** Build the server-environment key name for the configured API-key header. */
	private function envWithApiKey(string $value): HTTPEnvironment
	{
		$header_name = Settings::get('oz.auth', 'OZ_AUTH_API_KEY_HEADER_NAME');
		$server_key  = 'HTTP_' . \strtoupper(\str_replace('-', '_', $header_name));

		return HTTPEnvironment::mock([$server_key => $value]);
	}

	/**
	 * Creates a minimal RouteInfo backed by a real sub-context so that
	 * auth methods can call $ri->getContext()->getRequest()->getHeaderLine().
	 */
	private function makeRouteInfo(HTTPEnvironment $env): RouteInfo
	{
		$context = new Context($env, null, Context::root());

		return new RouteInfo($context, self::$route, []);
	}
}
