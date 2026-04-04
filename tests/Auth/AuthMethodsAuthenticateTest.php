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
use OZONE\Core\App\Keys;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Enums\AuthorizationState;
use OZONE\Core\Auth\Methods\ApiKeyHeaderAuth;
use OZONE\Core\Auth\Methods\BearerAuth;
use OZONE\Core\Auth\Providers\AuthUserAuthorizationProvider;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Router\Route;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Utils\Hasher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Regression tests for BearerAuth::authenticate() and ApiKeyHeaderAuth::authenticate().
 *
 * Security regression: before the fix both methods passed the raw credential
 * directly to Auth::getByTokenHash(), meaning the DB stored the raw value instead
 * of its SHA-256 hash.  The fix adds Hasher::hash64() before the lookup.
 *
 * Test strategy:
 *   a) Store a row with token_hash = raw_value (un-hashed).
 *      authenticate() with that raw_value -> ForbiddenException.
 *      Reason: the fixed code computes hash64(raw_value) which does NOT match
 *      the stored raw_value, so the lookup fails.
 *      If the bug were reintroduced (raw lookup), the row WOULD be found and
 *      the test would receive RuntimeException (user resolution), not
 *      ForbiddenException - meaning the assertion would fail and alert us.
 *
 *   b) Store a row with token_hash = hash64(raw_value).
 *      authenticate() with raw_value -> NOT ForbiddenException from the hash
 *      lookup step; it passes through to subsequent provider/user checks.
 *      We assert that the resulting exception is RuntimeException (from user
 *      resolution) and NOT ForbiddenException (which would mean the hash check
 *      incorrectly blocked the request).
 *
 * @internal
 *
 * @coversNothing
 */
final class AuthMethodsAuthenticateTest extends TestCase
{
	private static Router $router;
	private static Route $route;

	/**
	 * Creates all OZone DB tables in the in-memory SQLite instance.
	 *
	 * buildDatabase() emits DROP TABLE IF EXISTS before each CREATE TABLE
	 * so this is safe to call even when another test class ran first.
	 */
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		$sql = db()->getGenerator()->buildDatabase();
		db()->executeMulti($sql);

		self::$router = new Router();
		self::$router->get('/test-auth', static fn () => null)->name('test:auth');
		self::$route  = self::$router->getRoute('test:auth');
	}

	// -----------------------------------------------------------------------
	// BearerAuth::authenticate() - sanity
	// -----------------------------------------------------------------------

	public function testBearerAuthenticateThrowsForbiddenWhenTokenUnknown(): void
	{
		$this->expectException(ForbiddenException::class);

		$ri = $this->makeRouteInfo(HTTPEnvironment::mock([
			'HTTP_AUTHORIZATION' => 'Bearer ' . \str_repeat('z', 40),
		]));
		$method = BearerAuth::get($ri, 'test');
		$method->authenticate();
	}

	// -----------------------------------------------------------------------
	// BearerAuth::authenticate() - regression: hash IS applied (case a)
	// -----------------------------------------------------------------------

	/**
	 * Regression: before the fix, the raw token was used as the DB lookup value.
	 *
	 * A row is seeded where auth_token_hash equals the raw credential (not
	 * hashed).  With the bug the lookup would match, progressing past
	 * ForbiddenException.  With the fix the code does hash64(raw) which does
	 * NOT match the stored raw value -> row not found -> ForbiddenException.
	 */
	public function testBearerAuthenticateThrowsForbiddenWhenStoredValueIsUnhashed(): void
	{
		$raw_token = Keys::newAuthToken(); // 64-char random string
		$this->seedAuthRow($raw_token, $raw_token); // token_hash deliberately = raw (not hashed)

		$this->expectException(ForbiddenException::class);

		$ri = $this->makeRouteInfo(HTTPEnvironment::mock([
			'HTTP_AUTHORIZATION' => 'Bearer ' . $raw_token,
		]));
		BearerAuth::get($ri, 'test')->authenticate();
	}

	// -----------------------------------------------------------------------
	// BearerAuth::authenticate() - regression: hash IS applied (case b)
	// -----------------------------------------------------------------------

	/**
	 * Proves the token IS hashed before looking up: seeding with hash64(raw)
	 * makes the row discoverable when authenticate() is called with raw.
	 *
	 * The request gets past the hash-lookup step.  It then fails at user
	 * resolution (no repository is configured for the dummy owner type), which
	 * raises RuntimeException - NOT ForbiddenException "invalid token".
	 */
	public function testBearerAuthenticateHashedTokenIsFoundByAuthenticate(): void
	{
		$raw_token = Keys::newAuthToken();
		$this->seedAuthRow(Hasher::hash64($raw_token), $raw_token); // correctly hashed

		$ri = $this->makeRouteInfo(HTTPEnvironment::mock([
			'HTTP_AUTHORIZATION' => 'Bearer ' . $raw_token,
		]));

		try {
			BearerAuth::get($ri, 'test')->authenticate();
			// If we reach here the user was somehow resolved - unexpected in a pure
			// unit test with no user repository, but not a hash regression.
		} catch (ForbiddenException $e) {
			// A ForbiddenException with "Invalid auth token" message means the hash
			// lookup FAILED - this is the regression we want to catch.
			self::assertStringNotContainsString(
				'Invalid auth token.',
				(string) \json_encode($e->getData()),
				'Hash lookup failed - authenticate() is not hashing the token before lookup.'
			);
		} catch (RuntimeException) {
			// Expected path: hash lookup succeeded, user resolution failed
			// because no repository is configured for our dummy owner type.
			$this->addToAssertionCount(1);
		}
	}

	// -----------------------------------------------------------------------
	// ApiKeyHeaderAuth::authenticate() - sanity
	// -----------------------------------------------------------------------

	public function testApiKeyAuthenticateThrowsForbiddenWhenKeyUnknown(): void
	{
		$this->expectException(ForbiddenException::class);

		$ri = $this->makeRouteInfo($this->envWithApiKey(\str_repeat('z', 40)));
		ApiKeyHeaderAuth::get($ri, 'test')->authenticate();
	}

	// -----------------------------------------------------------------------
	// ApiKeyHeaderAuth::authenticate() - regression: hash IS applied (case a)
	// -----------------------------------------------------------------------

	/**
	 * Regression: same pattern as BearerAuth case a, for API-key header auth.
	 *
	 * Token_hash stored as raw -> authenticate() with raw -> ForbiddenException
	 * (because hash64(raw) != raw).
	 */
	public function testApiKeyAuthenticateThrowsForbiddenWhenStoredValueIsUnhashed(): void
	{
		$raw_key = Keys::newAuthToken();
		$this->seedAuthRow($raw_key, $raw_key); // token_hash = raw (un-hashed)

		$this->expectException(ForbiddenException::class);

		$ri = $this->makeRouteInfo($this->envWithApiKey($raw_key));
		ApiKeyHeaderAuth::get($ri, 'test')->authenticate();
	}

	// -----------------------------------------------------------------------
	// ApiKeyHeaderAuth::authenticate() - regression: hash IS applied (case b)
	// -----------------------------------------------------------------------

	/**
	 * Proves the API key IS hashed before lookup.  Same logic as the Bearer
	 * case b: seeding with hash64(raw) lets authenticate() find the row;
	 * failure at user resolution (RuntimeException) confirms the hash step worked.
	 */
	public function testApiKeyAuthenticateHashedKeyIsFoundByAuthenticate(): void
	{
		$raw_key = Keys::newAuthToken();
		$this->seedAuthRow(Hasher::hash64($raw_key), $raw_key);

		$ri = $this->makeRouteInfo($this->envWithApiKey($raw_key));

		try {
			ApiKeyHeaderAuth::get($ri, 'test')->authenticate();
		} catch (ForbiddenException $e) {
			self::assertStringNotContainsString(
				'Invalid auth token.',
				(string) \json_encode($e->getData()),
				'Hash lookup failed - authenticate() is not hashing the api key before lookup.'
			);
		} catch (RuntimeException) {
			// Expected: hash lookup worked, user resolution failed (no repository).
			$this->addToAssertionCount(1);
		}
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Inserts a minimal OZAuth row.
	 *
	 * @param string $token_hash value to store in auth_token_hash column
	 * @param string $_label     human note - not stored, only used to distinguish fixtures
	 */
	private function seedAuthRow(string $token_hash, string $_label): void
	{
		$auth = new OZAuth();
		$auth->setRef(Keys::newAuthToken()) // ref: min 32 chars
			->setLabel('regression-test')
			->setRefreshKey(Keys::newAuthToken()) // refresh_key: min 32 chars
			->setProvider(AuthUserAuthorizationProvider::NAME)
			->setCodeHash(Keys::newAuthToken()) // code_hash: any 32+ char string
			->setTokenHash($token_hash)
			->setState(AuthorizationState::AUTHORIZED)
			->setLifetime(3600)
			->setExpireAt((string) (\time() + 3600))
			->setOwnerType('dummy_user_type_no_repo')
			->setOwnerID('1');
		$auth->save();
	}

	private function makeRouteInfo(HTTPEnvironment $env): RouteInfo
	{
		$context = new Context($env, null, Context::root());

		return new RouteInfo($context, self::$route, []);
	}

	private function envWithApiKey(string $value): HTTPEnvironment
	{
		$header_name = Settings::get('oz.auth', 'OZ_AUTH_API_KEY_HEADER_NAME');
		$server_key  = 'HTTP_' . \strtoupper(\str_replace('-', '_', $header_name));

		return HTTPEnvironment::mock([$server_key => $value]);
	}
}
