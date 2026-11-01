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
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\AuthUserDataStore;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\LoginThrottle;
use OZONE\Core\Crypt\Password;
use OZONE\Core\Forms\FormDataClean;
use OZONE\Core\Stores\StateRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class LoginThrottleTest.
 *
 * Brute-force protection of password checks: {@see LoginThrottle} and
 * {@see AuthUsers::checkPassword()}.
 *
 * @internal
 */
#[CoversClass(AuthUsers::class)]
#[CoversClass(LoginThrottle::class)]
final class LoginThrottleTest extends TestCase
{
	private const string PASSWORD = 'right-pass-1';

	#[Override]
	protected function setUp(): void
	{
		StateRegistry::store(LoginThrottle::CACHE_NAMESPACE)->clear();
	}

	public function testLocksAfterMaxFailures(): void
	{
		$max = self::maxFailures();

		for ($i = 1; $i < $max; ++$i) {
			LoginThrottle::recordFailure('customer.1');
		}

		self::assertFalse(LoginThrottle::isLocked('customer.1'));

		LoginThrottle::recordFailure('customer.1');

		self::assertTrue(LoginThrottle::isLocked('customer.1'));
		self::assertSame($max, LoginThrottle::failures('customer.1'));
		self::assertFalse(LoginThrottle::isLocked('customer.2'));
	}

	public function testAnAttemptIsCountedWhereItIsLetThrough(): void
	{
		$max = self::maxFailures();

		// The claim is the check and the record in one step, and the total it reaches is what
		// decides: no attempt reads a count that another has not been added to yet.
		for ($i = 1; $i <= $max; ++$i) {
			self::assertTrue(LoginThrottle::claim('customer.3'), \sprintf('attempt %d is within the maximum', $i));
			self::assertSame($i, LoginThrottle::failures('customer.3'));
		}

		self::assertFalse(LoginThrottle::claim('customer.3'));
		self::assertTrue(LoginThrottle::isLocked('customer.3'));
		self::assertTrue(LoginThrottle::claim('customer.4'), 'another subject is untouched');
	}

	public function testAnAttemptThatNeverReportsItsFailureIsCountedAllTheSame(): void
	{
		$max = self::maxFailures();

		// This is what let a burst past the maximum: an attempt that was let through and then
		// recorded nothing -- a crash, a connection dropped while the password was being verified,
		// or simply a hundred attempts all checked before the first failure was written -- cost the
		// subject nothing, so the next one read the same count and was let through too.
		for ($i = 0; $i < $max; ++$i) {
			LoginThrottle::claim('customer.5');
		}

		self::assertFalse(LoginThrottle::claim('customer.5'));
	}

	public function testARightPasswordOnAnUnusableAccountIsNoGuessAndClearsTheCount(): void
	{
		$user = self::user(valid: false);

		AuthUsers::checkPassword($user, 'wrong', '');

		self::assertSame('OZ_AUTH_USER_UNVERIFIED', AuthUsers::checkPassword($user, self::PASSWORD, ''));

		// The password was right, so nothing was being guessed: counting it against the account
		// would lock out whoever has still to verify it, with the one password that works.
		self::assertSame(0, LoginThrottle::failures(AuthUsers::ref($user)));
	}

	public function testClearForgetsFailures(): void
	{
		LoginThrottle::recordFailure('customer.1');
		LoginThrottle::clear('customer.1');

		self::assertSame(0, LoginThrottle::failures('customer.1'));
	}

	public function testSubjectsAreCaseInsensitive(): void
	{
		LoginThrottle::recordFailure('customer|email|Bob@Example.com');

		self::assertSame(1, LoginThrottle::failures('customer|email|bob@example.com'));
	}

	public function testUnknownAccountAndWrongPasswordGetTheSameCode(): void
	{
		$user = self::user();

		self::assertSame('OZ_AUTH_INVALID_CREDENTIALS', AuthUsers::checkPassword($user, 'wrong', ''));
		self::assertSame('OZ_AUTH_INVALID_CREDENTIALS', AuthUsers::checkPassword(null, 'wrong', 'customer|email|nobody@example.com'));
	}

	public function testRightPasswordPassesAndClearsFailures(): void
	{
		$user = self::user();

		AuthUsers::checkPassword($user, 'wrong', '');
		self::assertSame(1, LoginThrottle::failures(AuthUsers::ref($user)));

		self::assertNull(AuthUsers::checkPassword($user, self::PASSWORD, ''));
		self::assertSame(0, LoginThrottle::failures(AuthUsers::ref($user)));
	}

	public function testUnverifiedIsOnlyRevealedAfterTheRightPassword(): void
	{
		$user = self::user(valid: false);

		self::assertSame('OZ_AUTH_INVALID_CREDENTIALS', AuthUsers::checkPassword($user, 'wrong', ''));
		self::assertSame('OZ_AUTH_USER_UNVERIFIED', AuthUsers::checkPassword($user, self::PASSWORD, ''));
	}

	public function testLockedAccountIsRejectedEvenWithTheRightPassword(): void
	{
		$user = self::user();

		for ($i = 0; $i < self::maxFailures(); ++$i) {
			AuthUsers::checkPassword($user, 'wrong', '');
		}

		self::assertSame('OZ_AUTH_TOO_MUCH_ATTEMPT', AuthUsers::checkPassword($user, self::PASSWORD, ''));
	}

	public function testUnknownIdentifiersAreLockedToo(): void
	{
		$subject = 'customer|email|nobody@example.com';

		for ($i = 0; $i < self::maxFailures(); ++$i) {
			AuthUsers::checkPassword(null, 'wrong', $subject);
		}

		// Same answer as for an existing locked account: failures reveal nothing.
		self::assertSame('OZ_AUTH_TOO_MUCH_ATTEMPT', AuthUsers::checkPassword(null, 'wrong', $subject));
	}

	public function testIdentifyBySelectorAcceptsCleanedData(): void
	{
		// Login passes the cleaned login form, which is not a FormData.
		self::assertNull(AuthUsers::identifyBySelector(new FormDataClean([
			AuthUsers::FIELD_AUTH_USER_TYPE => 'no-such-user-type',
			AuthUsers::FIELD_AUTH_USER_ID   => '1',
		])));
	}

	private static function maxFailures(): int
	{
		return (int) Settings::get('oz.auth', 'OZ_AUTH_LOGIN_MAX_FAILURES');
	}

	private static function user(bool $valid = true): AuthUserInterface
	{
		return new class(Password::hash(self::PASSWORD), $valid) implements AuthUserInterface {
			private ?AuthUserDataStore $data_store = null;

			public function __construct(private readonly string $hash, private readonly bool $valid) {}

			#[Override]
			public function getAuthUserType(): string
			{
				return 'customer';
			}

			#[Override]
			public function getAuthIdentifier(): string
			{
				return '42';
			}

			#[Override]
			public function getAuthIdentifiers(): array
			{
				return [];
			}

			#[Override]
			public function getAuthPassword(): string
			{
				return $this->hash;
			}

			#[Override]
			public function setAuthPassword(string $password_hash): static
			{
				return $this;
			}

			#[Override]
			public function getAuthUserDataStore(): AuthUserDataStore
			{
				return $this->data_store ??= AuthUserDataStore::getInstance($this, []);
			}

			#[Override]
			public function setAuthUserDataStore(AuthUserDataStore $store): static
			{
				$this->data_store = $store;

				return $this;
			}

			#[Override]
			public function isAuthUserValid(): bool
			{
				return $this->valid;
			}

			#[Override]
			public function save(): bool
			{
				return true;
			}

			#[Override]
			public function toArray(): array
			{
				return ['type' => 'customer', 'id' => '42'];
			}

			#[Override]
			public function jsonSerialize(): mixed
			{
				return $this->toArray();
			}
		};
	}
}
