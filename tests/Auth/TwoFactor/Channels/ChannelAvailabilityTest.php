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

namespace OZONE\Tests\Auth\TwoFactor\Channels;

use OZONE\Core\Auth\AuthUserDataStore;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\TwoFactor\Channels\EmailOtpChannel;
use OZONE\Core\Auth\TwoFactor\Channels\SmsOtpChannel;
use OZONE\Core\Auth\TwoFactor\Channels\TotpChannel;
use OZONE\Core\Auth\TwoFactor\TOTP;
use PHPUnit\Framework\TestCase;

/**
 * Tests the isAvailableFor() logic of each built-in 2FA channel.
 *
 * @internal
 *
 * @coversNothing
 */
final class ChannelAvailabilityTest extends TestCase
{
	private static int $userSeq = 0;

	// EmailOtpChannel ------------------------------------------------------

	public function testEmailChannelNameIsEmail(): void
	{
		self::assertSame('email', EmailOtpChannel::getName());
	}

	public function testEmailChannelIsAvailableWhenUserHasEmail(): void
	{
		$channel = new EmailOtpChannel();
		$user    = $this->makeUser(email: 'user@example.com');
		self::assertTrue($channel->isAvailableFor($user));
	}

	public function testEmailChannelIsUnavailableWhenEmailIsEmpty(): void
	{
		$channel = new EmailOtpChannel();
		$user    = $this->makeUser(email: '');
		self::assertFalse($channel->isAvailableFor($user));
	}

	public function testEmailChannelIsUnavailableWhenEmailKeyIsAbsent(): void
	{
		$user = $this->createMock(AuthUserInterface::class);
		$user->method('getAuthIdentifiers')->willReturn([]);

		$channel = new EmailOtpChannel();
		self::assertFalse($channel->isAvailableFor($user));
	}

	// SmsOtpChannel --------------------------------------------------------

	public function testSmsChannelNameIsSms(): void
	{
		self::assertSame('sms', SmsOtpChannel::getName());
	}

	public function testSmsChannelIsAvailableWhenUserHasPhone(): void
	{
		$channel = new SmsOtpChannel();
		$user    = $this->makeUser(phone: '+1234567890');
		self::assertTrue($channel->isAvailableFor($user));
	}

	public function testSmsChannelIsUnavailableWhenPhoneIsEmpty(): void
	{
		$channel = new SmsOtpChannel();
		$user    = $this->makeUser(phone: '');
		self::assertFalse($channel->isAvailableFor($user));
	}

	public function testSmsChannelIsUnavailableWhenPhoneKeyIsAbsent(): void
	{
		$user = $this->createMock(AuthUserInterface::class);
		$user->method('getAuthIdentifiers')->willReturn([]);

		$channel = new SmsOtpChannel();
		self::assertFalse($channel->isAvailableFor($user));
	}

	// TotpChannel ----------------------------------------------------------

	public function testTotpChannelNameIsTotp(): void
	{
		self::assertSame('totp', TotpChannel::getName());
	}

	public function testTotpChannelIsAvailableWhenUserHasSecret(): void
	{
		$channel = new TotpChannel();
		$user    = $this->makeUser(totp_secret: TOTP::generateSecret());
		self::assertTrue($channel->isAvailableFor($user));
	}

	public function testTotpChannelIsUnavailableWhenSecretIsNull(): void
	{
		$channel = new TotpChannel();
		$user    = $this->makeUser(); // no TOTP secret set
		self::assertFalse($channel->isAvailableFor($user));
	}

	public function testTotpChannelEachChannelNameIsDistinct(): void
	{
		self::assertNotSame(EmailOtpChannel::getName(), SmsOtpChannel::getName());
		self::assertNotSame(EmailOtpChannel::getName(), TotpChannel::getName());
		self::assertNotSame(SmsOtpChannel::getName(), TotpChannel::getName());
	}

	/**
	 * Builds a mock user with the given identifiers and optional TOTP secret.
	 */
	private function makeUser(
		string $email = '',
		string $phone = '',
		?string $totp_secret = null
	): AuthUserInterface {
		$id   = 'ch-avail-user-' . ++self::$userSeq;
		$user = $this->createMock(AuthUserInterface::class);
		$user->method('getAuthUserType')->willReturn('test');
		$user->method('getAuthIdentifier')->willReturn($id);
		$user->method('getAuthIdentifiers')->willReturn([
			AuthUserInterface::IDENTIFIER_TYPE_EMAIL => $email,
			AuthUserInterface::IDENTIFIER_TYPE_PHONE => $phone,
		]);

		$store = AuthUserDataStore::getInstance($user, []);

		if (null !== $totp_secret) {
			$store->set2FATotpSecret($totp_secret);
		}

		$user->method('getAuthUserDataStore')->willReturn($store);

		return $user;
	}
}
