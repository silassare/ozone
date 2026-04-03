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

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\Auth\Auth;
use OZONE\Core\Auth\AuthorizationScope;
use OZONE\Core\Auth\Enums\AuthorizationSecretType;
use OZONE\Core\Auth\Enums\AuthorizationState;
use OZONE\Core\Auth\Providers\AuthorizationProvider;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\UnauthorizedException;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Utils\Hasher;
use PHPUnit\Framework\TestCase;

/**
 * Tests the AuthorizationProvider state machine.
 *
 * A lightweight anonymous subclass is used so no email / SMS / user-repo
 * side effects are triggered. The in-memory SQLite DB (configured in
 * tests/settings/oz.db.php) is used for all persistence assertions.
 *
 * @internal
 *
 * @coversNothing
 */
final class AuthorizationProviderTest extends TestCase
{
	/**
	 * Create all OZone DB tables in the in-memory SQLite instance.
	 *
	 * The test bootstrap (tests/autoload.php) calls OZone::bootstrap() which
	 * registers the schema but does NOT execute any DDL.  We generate the
	 * full CREATE TABLE SQL via the Gobl query generator and run it here so
	 * that every test in this class can persist OZAuth rows.
	 */
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		$sql = db()->getGenerator()->buildDatabase();
		db()->executeMulti($sql);
	}

	// -----------------------------------------------------------------------
	// generate()
	// -----------------------------------------------------------------------

	public function testGenerateCreatesAuthRecordInDB(): void
	{
		$provider = $this->makeProvider();
		$provider->generate();

		$ref = $provider->getCredentials()->getReference();
		self::assertNotEmpty($ref);

		$auth = Auth::get($ref);
		self::assertNotNull($auth);
	}

	/**
	 * The token stored in the DB must be hash(token), not the raw token.
	 *
	 * Regression: before the BearerAuth fix, BearerAuth::authenticate() called
	 * Auth::getByTokenHash($raw_token) which would never find the record because
	 * the DB stores hash($raw_token).  After the fix it calls
	 * Auth::getByTokenHash(Hasher::hash64($raw_token)).
	 */
	public function testGenerateStoresHashedTokenNotRawToken(): void
	{
		$provider = $this->makeProvider();
		$provider->generate();

		$token = $provider->getCredentials()->getToken();
		$ref   = $provider->getCredentials()->getReference();

		$auth = Auth::get($ref);
		self::assertNotNull($auth);

		// Correct lookup: hash the received token before querying.
		$found_correct = Auth::getByTokenHash(Hasher::hash64($token));
		self::assertNotNull($found_correct, 'hash(token) lookup must find the record');

		// Wrong lookup: raw token should NOT match the stored hash(token).
		$found_raw = Auth::getByTokenHash($token);
		self::assertNull($found_raw, 'raw token lookup must NOT find the record');
	}

	public function testGenerateStoresHashedCodeNotRawCode(): void
	{
		$provider = $this->makeProvider();
		$provider->generate();

		$code = $provider->getCredentials()->getCode();
		$ref  = $provider->getCredentials()->getReference();

		$auth = Auth::get($ref);
		self::assertNotNull($auth);

		// Code hash in DB must equal hash(code), not the code itself.
		self::assertSame(Hasher::hash64($code), $auth->getCodeHash());
		self::assertNotSame($code, $auth->getCodeHash());
	}

	public function testGenerateInitialStateIsPending(): void
	{
		$provider = $this->makeProvider();
		$provider->generate();

		$auth = Auth::get($provider->getCredentials()->getReference());
		self::assertNotNull($auth);
		self::assertSame(AuthorizationState::PENDING, $auth->getState());
	}

	public function testGenerateInitialTryCountIsZero(): void
	{
		$provider = $this->makeProvider();
		$provider->generate();

		$auth = Auth::get($provider->getCredentials()->getReference());
		self::assertNotNull($auth);
		self::assertSame(0, $auth->getTryCount());
	}

	// -----------------------------------------------------------------------
	// authorize() - success
	// -----------------------------------------------------------------------

	public function testAuthorizeWithCorrectCodeSetsStateToAuthorized(): void
	{
		$provider = $this->makeProvider();
		$provider->generate();

		$ref  = $provider->getCredentials()->getReference();
		$code = $provider->getCredentials()->getCode();

		$provider->getCredentials()
			->setReference($ref)
			->setCode($code);

		$provider->authorize(AuthorizationSecretType::CODE);

		$auth = Auth::get($ref);
		self::assertNotNull($auth);
		self::assertSame(AuthorizationState::AUTHORIZED, $auth->getState());
	}

	public function testAuthorizeWithCorrectTokenSetsStateToAuthorized(): void
	{
		$provider = $this->makeProvider();
		$provider->generate();

		$ref   = $provider->getCredentials()->getReference();
		$token = $provider->getCredentials()->getToken();

		$provider->getCredentials()
			->setReference($ref)
			->setToken($token);

		$provider->authorize(AuthorizationSecretType::TOKEN);

		$auth = Auth::get($ref);
		self::assertNotNull($auth);
		self::assertSame(AuthorizationState::AUTHORIZED, $auth->getState());
	}

	// -----------------------------------------------------------------------
	// authorize() - wrong secret / retry logic
	// -----------------------------------------------------------------------

	public function testAuthorizeWithWrongCodeIncrementsTryCount(): void
	{
		$provider = $this->makeProvider(try_max: 3);
		$provider->generate();

		$ref = $provider->getCredentials()->getReference();

		$provider->getCredentials()
			->setReference($ref)
			->setCode('wrong-code');

		$this->expectException(InvalidFormException::class);
		$provider->authorize(AuthorizationSecretType::CODE);

		$auth = Auth::get($ref);
		self::assertNotNull($auth);
		self::assertSame(1, $auth->getTryCount());
		self::assertSame(AuthorizationState::PENDING, $auth->getState());
	}

	public function testAuthorizeWithWrongCodeUntilTryMaxSetsStateToRefused(): void
	{
		$provider = $this->makeProvider(try_max: 2);
		$provider->generate();

		$ref = $provider->getCredentials()->getReference();

		for ($i = 0; $i < 2; ++$i) {
			$provider->getCredentials()
				->setReference($ref)
				->setCode('wrong');

			try {
				$provider->authorize(AuthorizationSecretType::CODE);
			} catch (InvalidFormException) {
				// expected on wrong code attempts that have remaining retries
			} catch (UnauthorizedException) {
				// expected on the LAST wrong attempt: onTooMuchRetry() throws this
			}
		}

		$auth = Auth::get($ref);
		self::assertNotNull($auth);
		self::assertSame(AuthorizationState::REFUSED, $auth->getState());
	}

	// -----------------------------------------------------------------------
	// authorize() - expired
	// -----------------------------------------------------------------------

	public function testAuthorizeOnExpiredAuthThrowsUnauthorizedException(): void
	{
		$provider = $this->makeProvider();
		$provider->generate();

		$ref = $provider->getCredentials()->getReference();

		// Back-date the expiry via direct SQL to reliably bypass any ORM caching
		// or entity-state assumptions tied to the PK type.
		db()->update(
			'UPDATE ' . OZAuth::TABLE_NAME . ' SET ' . OZAuth::COL_EXPIRE_AT . ' = ? WHERE ' . OZAuth::COL_REF . ' = ?',
			[(string) (\time() - 300), $ref]
		);

		$provider->getCredentials()
			->setReference($ref)
			->setCode($provider->getCredentials()->getCode());

		$this->expectException(UnauthorizedException::class);
		$provider->authorize(AuthorizationSecretType::CODE);
	}

	// -----------------------------------------------------------------------
	// refresh()
	// -----------------------------------------------------------------------

	public function testRefreshWithValidRefreshKeyResetsState(): void
	{
		$provider = $this->makeProvider();
		$provider->generate();

		$ref         = $provider->getCredentials()->getReference();
		$refresh_key = $provider->getCredentials()->getRefreshKey();

		$provider->getCredentials()
			->setReference($ref)
			->setRefreshKey($refresh_key);

		$provider->refresh();

		$auth = Auth::get($ref);
		self::assertNotNull($auth);
		self::assertSame(AuthorizationState::PENDING, $auth->getState());
		self::assertSame(0, $auth->getTryCount());
	}

	/**
	 * Regression: before the dead-code fix in refresh(), setTryCount() was called
	 * with getLifetime() (a seconds value like 60) before being immediately
	 * overwritten with 0.  The fix changes the first call to setLifetime() so
	 * the lifetime is correctly updated on refresh.
	 */
	public function testRefreshCorrectlyUpdatesLifetimeNotTryCount(): void
	{
		$provider = $this->makeProvider();
		$provider->generate();

		$ref         = $provider->getCredentials()->getReference();
		$refresh_key = $provider->getCredentials()->getRefreshKey();

		// Read the original lifetime from the freshly generated record.
		$auth_before = Auth::get($ref);
		self::assertNotNull($auth_before);
		$original_lifetime = $auth_before->getLifetime();

		$provider->getCredentials()
			->setReference($ref)
			->setRefreshKey($refresh_key);

		$provider->refresh();

		$auth_after = Auth::get($ref);
		self::assertNotNull($auth_after);

		// try_count must be reset to 0, NOT to the lifetime seconds.
		self::assertSame(0, $auth_after->getTryCount());

		// lifetime must remain the same (scope lifetime, 60 s in this test).
		self::assertSame($original_lifetime, $auth_after->getLifetime());
	}

	public function testRefreshWithInvalidRefreshKeyThrows(): void
	{
		$provider = $this->makeProvider();
		$provider->generate();

		$ref = $provider->getCredentials()->getReference();

		$provider->getCredentials()
			->setReference($ref)
			->setRefreshKey('this-is-not-the-correct-refresh-key');

		$this->expectException(InvalidFormException::class);
		$provider->refresh();
	}

	// -----------------------------------------------------------------------
	// cancel()
	// -----------------------------------------------------------------------

	/**
	 * Regression: before the fix, cancel() did not check the refresh_key, so any
	 * client that knew the ref could delete any pending authorization flow.
	 */
	public function testCancelWithValidRefreshKeyDeletesAuthRecord(): void
	{
		$provider = $this->makeProvider();
		$provider->generate();

		$ref         = $provider->getCredentials()->getReference();
		$refresh_key = $provider->getCredentials()->getRefreshKey();

		$provider->getCredentials()
			->setReference($ref)
			->setRefreshKey($refresh_key);

		$provider->cancel();

		// Record should be gone from the DB.
		$auth = Auth::get($ref);
		self::assertNull($auth);
	}

	/**
	 * Regression: before the fix, cancel() had no refresh_key guard, meaning
	 * anyone who knew the ref could cancel any pending auth flow (DoS).
	 */
	public function testCancelWithInvalidRefreshKeyIsRejected(): void
	{
		$provider = $this->makeProvider();
		$provider->generate();

		$ref = $provider->getCredentials()->getReference();

		$provider->getCredentials()
			->setReference($ref)
			->setRefreshKey('wrong-refresh-key');

		$this->expectException(InvalidFormException::class);
		$provider->cancel();

		// The record must still exist after the failed cancel attempt.
		$auth = Auth::get($ref);
		self::assertNotNull($auth);
	}

	// -----------------------------------------------------------------------
	// getState()
	// -----------------------------------------------------------------------

	public function testGetStateReturnsPendingForFreshRecord(): void
	{
		$provider = $this->makeProvider();
		$provider->generate();

		$ref = $provider->getCredentials()->getReference();
		$provider->getCredentials()->setReference($ref);

		self::assertSame(AuthorizationState::PENDING, $provider->getState());
	}

	public function testGetStateThrowsNotFoundForUnknownRef(): void
	{
		$provider = $this->makeProvider();
		$provider->getCredentials()->setReference('does-not-exist');

		$this->expectException(NotFoundException::class);
		$provider->getState();
	}

	// -----------------------------------------------------------------------
	// Helper: concrete test provider (no side effects)
	// -----------------------------------------------------------------------

	/** Creates a no-op provider with a 60 s lifetime and configurable try_max. */
	private function makeProvider(int $try_max = 3): AuthorizationProvider
	{
		$context = new Context(HTTPEnvironment::mock(), null, Context::root());

		$provider = new class($context) extends AuthorizationProvider {
			#[Override]
			public static function getName(): string
			{
				return 'test:provider:state-machine';
			}

			#[Override]
			public static function resolve(Context $context, OZAuth $auth): static
			{
				return new self($context);
			}

			#[Override]
			public function getPayload(): array
			{
				return [];
			}
		};

		$scope = new AuthorizationScope();
		$scope->setLabel('Test authorization')
			->setLifetime(60)
			->setTryMax($try_max);
		$provider->setScope($scope);

		return $provider;
	}
}
