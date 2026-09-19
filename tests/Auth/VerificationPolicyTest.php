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

use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Providers\EmailOwnershipVerificationProvider;
use OZONE\Core\Auth\Providers\PhoneOwnershipVerificationProvider;
use OZONE\Core\Auth\VerificationPolicy;
use PHPUnit\Framework\TestCase;

/**
 * What a user type must prove before signing up or recovering an account (`oz.auth.verification`).
 *
 * @internal
 *
 * @covers \OZONE\Core\Auth\VerificationPolicy
 */
final class VerificationPolicyTest extends TestCase
{
	private const EMAIL = EmailOwnershipVerificationProvider::NAME;

	private const PHONE = PhoneOwnershipVerificationProvider::NAME;

	protected function tearDown(): void
	{
		// Back to what the package ships, for whatever runs next.
		Settings::set('oz.auth.verification', VerificationPolicy::SIGN_UP, [
			VerificationPolicy::ANY_USER_TYPE => [self::EMAIL, self::PHONE],
		]);

		parent::tearDown();
	}

	public function testEmailAndPhoneAreWhatEveryTypeMayProveByDefault(): void
	{
		self::assertSame(
			[self::EMAIL, self::PHONE],
			VerificationPolicy::providersFor(VerificationPolicy::SIGN_UP, 'user')
		);
		self::assertSame(
			[self::EMAIL, self::PHONE],
			VerificationPolicy::providersFor(VerificationPolicy::ACCOUNT_RECOVERY, 'user')
		);
	}

	public function testATypeFallsBackToTheRuleOfEveryType(): void
	{
		Settings::set('oz.auth.verification', VerificationPolicy::SIGN_UP, [
			VerificationPolicy::ANY_USER_TYPE => [self::EMAIL],
			'staff'                           => [self::PHONE],
		]);

		self::assertSame([self::PHONE], VerificationPolicy::providersFor(VerificationPolicy::SIGN_UP, 'staff'));
		self::assertSame([self::EMAIL], VerificationPolicy::providersFor(VerificationPolicy::SIGN_UP, 'user'));
		self::assertSame(
			[self::EMAIL],
			VerificationPolicy::providersFor(VerificationPolicy::SIGN_UP, 'whatever-else')
		);
	}

	public function testATypeMayHaveNothingToProve(): void
	{
		Settings::set('oz.auth.verification', VerificationPolicy::SIGN_UP, [
			VerificationPolicy::ANY_USER_TYPE => [self::EMAIL],
			'guest'                           => [],
		]);

		self::assertSame([], VerificationPolicy::providersFor(VerificationPolicy::SIGN_UP, 'guest'));
		self::assertSame([self::EMAIL], VerificationPolicy::providersFor(VerificationPolicy::SIGN_UP, 'user'));
	}

	public function testTheRouteAsksForOneOnlyWhenEveryTypeMustProveSomething(): void
	{
		self::assertTrue(VerificationPolicy::alwaysRequired(VerificationPolicy::SIGN_UP));

		Settings::set('oz.auth.verification', VerificationPolicy::SIGN_UP, [
			VerificationPolicy::ANY_USER_TYPE => [self::EMAIL],
			'guest'                           => [],
		]);

		self::assertFalse(
			VerificationPolicy::alwaysRequired(VerificationPolicy::SIGN_UP),
			'one type that proves nothing leaves the door open, and the handler decides'
		);

		// The fallback is emptied rather than left out: a settings group merges key by key, so a rule
		// that is not mentioned is the one the package ships.
		Settings::set('oz.auth.verification', VerificationPolicy::SIGN_UP, [
			VerificationPolicy::ANY_USER_TYPE => [],
			'staff'                           => [self::EMAIL],
		]);

		self::assertFalse(VerificationPolicy::alwaysRequired(VerificationPolicy::SIGN_UP));
		self::assertSame([], VerificationPolicy::providersFor(VerificationPolicy::SIGN_UP, 'user'));
		self::assertSame(
			[self::EMAIL],
			VerificationPolicy::providersFor(VerificationPolicy::SIGN_UP, 'staff')
		);
	}

	public function testTheDoorAcceptsEveryProviderNamedInARule(): void
	{
		Settings::set('oz.auth.verification', VerificationPolicy::SIGN_UP, [
			VerificationPolicy::ANY_USER_TYPE => [self::EMAIL],
			'staff'                           => [self::PHONE, self::EMAIL],
		]);

		$all = VerificationPolicy::allProviders(VerificationPolicy::SIGN_UP);

		\sort($all);

		$expected = [self::EMAIL, self::PHONE];

		\sort($expected);

		self::assertSame($expected, $all, 'named once, whatever how many rules name it');
	}

	public function testAProjectNamesItsOwnProvider(): void
	{
		Settings::set('oz.auth.verification', VerificationPolicy::SIGN_UP, [
			VerificationPolicy::ANY_USER_TYPE => ['app:provider:invitation'],
		]);

		self::assertSame(
			['app:provider:invitation'],
			VerificationPolicy::providersFor(VerificationPolicy::SIGN_UP, 'user')
		);
		self::assertTrue(VerificationPolicy::alwaysRequired(VerificationPolicy::SIGN_UP));
	}
}
