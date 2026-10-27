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
use OZONE\Core\Auth\Auth;
use OZONE\Core\Auth\Enums\AuthorizationState;
use OZONE\Core\Auth\Providers\EmailOwnershipVerificationProvider;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Exceptions\UnauthorizedException;
use OZONE\Core\Http\HTTPEnvironment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests the provider `Auth::provider()` rebuilds for an existing authorization.
 *
 * @internal
 */
#[CoversClass(Auth::class)]
final class AuthProviderTest extends TestCase
{
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		$sql = db()->getGenerator()->buildDatabase();
		db()->executeMulti($sql);
	}

	/**
	 * A provider's credentials draw a new reference when built: the rebuilt provider must carry the
	 * authorization's own, or every lookup it makes misses the row.
	 */
	public function testTheProviderCarriesTheAuthorizationReference(): void
	{
		$auth = $this->seedAuth(AuthorizationState::PENDING);

		$provider = Auth::provider($this->context(), $auth);

		self::assertSame($auth->getRef(), $provider->getCredentials()->getReference());
	}

	/**
	 * What the authorization guard of a route asks: an authorization already confirmed is seen as such.
	 */
	public function testTheProviderReadsTheStateOfTheAuthorization(): void
	{
		$authorized = $this->seedAuth(AuthorizationState::AUTHORIZED);
		$pending    = $this->seedAuth(AuthorizationState::PENDING);

		self::assertSame(
			AuthorizationState::AUTHORIZED,
			Auth::provider($this->context(), $authorized)->getState()
		);
		self::assertSame(
			AuthorizationState::PENDING,
			Auth::provider($this->context(), $pending)->getState()
		);
	}

	/**
	 * An authorization granted before it expired grants nothing after, though its row is still there
	 * until the garbage collection deletes it.
	 */
	public function testAnExpiredAuthorizationHasNoState(): void
	{
		$auth = $this->seedAuth(AuthorizationState::AUTHORIZED, \time() - 1);

		$this->expectException(UnauthorizedException::class);
		$this->expectExceptionMessage('OZ_AUTH_HAS_EXPIRED');

		Auth::provider($this->context(), $auth)->getState();
	}

	private function seedAuth(AuthorizationState $state, ?int $expire_at = null): OZAuth
	{
		$auth = new OZAuth();
		$auth->setRef(Keys::newAuthReference())
			->setLabel('auth-provider-test')
			->setRefreshKey(Keys::newAuthToken())
			->setProvider(EmailOwnershipVerificationProvider::NAME)
			->setPayload(['email' => 'jane.doe@example.com'])
			->setCodeHash(Keys::newAuthToken())
			->setTokenHash(Keys::newAuthToken())
			->setState($state)
			->setLifetime(3600)
			->setExpireAt((string) ($expire_at ?? \time() + 3600));
		$auth->save();

		return $auth;
	}

	private function context(): Context
	{
		return new Context(HTTPEnvironment::mock(), null, Context::root());
	}
}
