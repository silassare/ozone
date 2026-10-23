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
use OZONE\Core\Auth\Enums\VerificationPurpose;
use OZONE\Core\Auth\Providers\EmailOwnershipVerificationProvider;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Http\HTTPEnvironment;
use PHPUnit\Framework\TestCase;

/**
 * Tests what an email or phone ownership verification carries: its purpose, and the time the owner
 * has to answer it.
 *
 * @internal
 *
 * @covers \OZONE\Core\Auth\Providers\OwnershipVerificationProvider
 */
final class OwnershipVerificationTest extends TestCase
{
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		$sql = db()->getGenerator()->buildDatabase();
		db()->executeMulti($sql);
	}

	/**
	 * The owner has longer to confirm a sign-up, which creates nothing until answered, than to reset
	 * the password of an account that already exists.
	 *
	 * @dataProvider provideEachPurposeHasItsOwnLifetimeCases
	 */
	public function testEachPurposeHasItsOwnLifetime(VerificationPurpose $purpose, int $lifetime): void
	{
		$provider = new EmailOwnershipVerificationProvider($this->context(), 'jane.doe@example.com', $purpose);

		self::assertSame($lifetime, $provider->getScope()->getLifetime());
	}

	/**
	 * @return iterable<string, array{0: VerificationPurpose, 1: int}>
	 */
	public static function provideEachPurposeHasItsOwnLifetimeCases(): iterable
	{
		yield 'a plain check' => [VerificationPurpose::CHECK, 3600];

		yield 'a sign-up' => [VerificationPurpose::SIGN_UP, 86400];

		yield 'an account recovery' => [VerificationPurpose::ACCOUNT_RECOVERY, 3600];
	}

	/**
	 * The purpose is what the owner was told, so it is read back from the authorization, never from
	 * the request that presents it.
	 */
	public function testThePurposeSurvivesARebuild(): void
	{
		$auth = $this->seedAuth(['email' => 'jane.doe@example.com', 'purpose' => 'account-recovery', 'user_type' => 'user']);

		$provider = Auth::provider($this->context(), $auth);

		self::assertInstanceOf(EmailOwnershipVerificationProvider::class, $provider);
		self::assertSame(VerificationPurpose::ACCOUNT_RECOVERY, $provider->getPurpose());
		self::assertSame('user', $provider->getUserType());
		self::assertSame('jane.doe@example.com', $provider->getIdentifier());
	}

	/**
	 * An authorization saved before purposes existed proves an address and nothing more: it recovers
	 * no account.
	 */
	public function testAnAuthorizationWithoutAPurposeIsAPlainCheck(): void
	{
		$auth = $this->seedAuth(['email' => 'jane.doe@example.com']);

		$provider = Auth::provider($this->context(), $auth);

		self::assertInstanceOf(EmailOwnershipVerificationProvider::class, $provider);
		self::assertSame(VerificationPurpose::CHECK, $provider->getPurpose());
		self::assertNull($provider->getUserType());
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function seedAuth(array $payload): OZAuth
	{
		$auth = new OZAuth();
		$auth->setRef(Keys::newAuthReference())
			->setLabel('ownership-verification-test')
			->setRefreshKey(Keys::newAuthToken())
			->setProvider(EmailOwnershipVerificationProvider::NAME)
			->setPayload($payload)
			->setCodeHash(Keys::newAuthToken())
			->setTokenHash(Keys::newAuthToken())
			->setState(AuthorizationState::PENDING)
			->setLifetime(3600)
			->setExpireAt((string) (\time() + 3600));
		$auth->save();

		return $auth;
	}

	private function context(): Context
	{
		return new Context(HTTPEnvironment::mock(), null, Context::root());
	}
}
