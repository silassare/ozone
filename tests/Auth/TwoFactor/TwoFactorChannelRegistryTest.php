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

namespace OZONE\Tests\Auth\TwoFactor;

use OZONE\Core\Auth\AuthUserDataStore;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\TwoFactor\Channels\EmailOtpChannel;
use OZONE\Core\Auth\TwoFactor\Channels\SmsOtpChannel;
use OZONE\Core\Auth\TwoFactor\Channels\TotpChannel;
use OZONE\Core\Auth\TwoFactor\TOTP;
use OZONE\Core\Auth\TwoFactor\TwoFactorChannelInterface;
use OZONE\Core\Auth\TwoFactor\TwoFactorChannelRegistry;
use OZONE\Core\Exceptions\RuntimeException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 *
 * @coversNothing
 */
final class TwoFactorChannelRegistryTest extends TestCase
{
	/** @var array<string, TwoFactorChannelInterface> */
	private array $channels_before;

	private static int $userSeq = 0;

	/**
	 * Saves the current channel map and resets the registry to an empty state
	 * so each test starts with a clean slate.
	 */
	protected function setUp(): void
	{
		$ref  = new ReflectionClass(TwoFactorChannelRegistry::class);
		$prop = $ref->getProperty('channels');
		$prop->setAccessible(true);
		$this->channels_before = $prop->getValue(null);
		$prop->setValue(null, []);
	}

	/**
	 * Restores the channel map saved before the test ran.
	 */
	protected function tearDown(): void
	{
		$ref  = new ReflectionClass(TwoFactorChannelRegistry::class);
		$prop = $ref->getProperty('channels');
		$prop->setAccessible(true);
		$prop->setValue(null, $this->channels_before);
	}

	// register / get / all -------------------------------------------------

	public function testRegisterAndGetChannelByName(): void
	{
		$channel = new EmailOtpChannel();
		TwoFactorChannelRegistry::register($channel);
		self::assertSame($channel, TwoFactorChannelRegistry::get('email'));
	}

	public function testGetUnregisteredChannelReturnsNull(): void
	{
		self::assertNull(TwoFactorChannelRegistry::get('email'));
	}

	public function testAllReturnsEmptyArrayWhenNoChannelsRegistered(): void
	{
		self::assertSame([], TwoFactorChannelRegistry::all());
	}

	public function testAllReturnsEveryRegisteredChannel(): void
	{
		$email = new EmailOtpChannel();
		$sms   = new SmsOtpChannel();
		TwoFactorChannelRegistry::register($email);
		TwoFactorChannelRegistry::register($sms);

		$all = TwoFactorChannelRegistry::all();
		self::assertCount(2, $all);
		self::assertSame($email, $all['email']);
		self::assertSame($sms, $all['sms']);
	}

	public function testRegisterReplacesExistingChannelWithSameName(): void
	{
		$first  = new EmailOtpChannel();
		$second = new EmailOtpChannel();
		TwoFactorChannelRegistry::register($first);
		TwoFactorChannelRegistry::register($second);

		self::assertSame($second, TwoFactorChannelRegistry::get('email'));
		self::assertCount(1, TwoFactorChannelRegistry::all());
	}

	// selectFor ------------------------------------------------------------

	public function testSelectForUsesUserPreferredChannelWhenAvailable(): void
	{
		// User prefers 'email' and has an email address configured.
		$user = $this->makeUser(email: 'user@example.com', preferred_method: 'email');

		TwoFactorChannelRegistry::register(new EmailOtpChannel());
		TwoFactorChannelRegistry::register(new SmsOtpChannel());

		$selected = TwoFactorChannelRegistry::selectFor($user);
		self::assertSame('email', $selected::getName());
	}

	public function testSelectForSkipsPreferredChannelWhenNotAvailable(): void
	{
		// User prefers 'totp' but has no TOTP secret -> falls back to priority order.
		// Priority is ['totp', 'email', 'sms'] from tests/settings/oz.auth.2fa.php,
		// so email is tried next and succeeds.
		$user = $this->makeUser(email: 'user@example.com', has_totp_secret: false, preferred_method: 'totp');

		TwoFactorChannelRegistry::register(new TotpChannel());
		TwoFactorChannelRegistry::register(new EmailOtpChannel());

		$selected = TwoFactorChannelRegistry::selectFor($user);
		self::assertSame('email', $selected::getName());
	}

	public function testSelectForIgnoresPreferredChannelNotRegistered(): void
	{
		// User prefers 'sms' but that channel has not been registered.
		$user = $this->makeUser(email: 'user@example.com', phone: '+1234567890', preferred_method: 'sms');

		// Only email is registered.
		TwoFactorChannelRegistry::register(new EmailOtpChannel());

		$selected = TwoFactorChannelRegistry::selectFor($user);
		self::assertSame('email', $selected::getName());
	}

	public function testSelectForFollowsPriorityOrderWithNoPreference(): void
	{
		// Priority: ['totp', 'email', 'sms']. User has no TOTP secret, totp is skipped.
		// Email is available -> email should win.
		$user = $this->makeUser(email: 'user@example.com');

		TwoFactorChannelRegistry::register(new TotpChannel());
		TwoFactorChannelRegistry::register(new EmailOtpChannel());
		TwoFactorChannelRegistry::register(new SmsOtpChannel());

		$selected = TwoFactorChannelRegistry::selectFor($user);
		self::assertSame('email', $selected::getName());
	}

	public function testSelectForPicksTotpWhenSecretIsConfiguredAndHasHighPriority(): void
	{
		// Priority: ['totp', 'email', 'sms']. User has a TOTP secret -> totp wins.
		$user = $this->makeUser(email: 'user@example.com', has_totp_secret: true);

		TwoFactorChannelRegistry::register(new TotpChannel());
		TwoFactorChannelRegistry::register(new EmailOtpChannel());

		$selected = TwoFactorChannelRegistry::selectFor($user);
		self::assertSame('totp', $selected::getName());
	}

	public function testSelectForThrowsRuntimeExceptionWhenNoChannelAvailable(): void
	{
		// No channels registered at all.
		$user = $this->makeUser(email: 'user@example.com');

		$this->expectException(RuntimeException::class);
		TwoFactorChannelRegistry::selectFor($user);
	}

	public function testSelectForThrowsWhenAllRegisteredChannelsUnavailable(): void
	{
		// Totp channel registered but user has no TOTP secret -> unavailable.
		$user = $this->makeUser(has_totp_secret: false);

		TwoFactorChannelRegistry::register(new TotpChannel());

		$this->expectException(RuntimeException::class);
		TwoFactorChannelRegistry::selectFor($user);
	}

	// Helpers --------------------------------------------------------------

	/**
	 * Creates a mock user whose getAuthIdentifiers() includes the given values.
	 *
	 * Each call produces a unique user ref so the AuthUserDataStore runtime
	 * cache always returns a fresh instance.
	 */
	private function makeUser(
		string $email = '',
		string $phone = '',
		bool $has_totp_secret = false,
		?string $preferred_method = null
	): AuthUserInterface {
		$id   = 'registry-test-user-' . ++self::$userSeq;
		$user = $this->createMock(AuthUserInterface::class);
		$user->method('getAuthUserType')->willReturn('test');
		$user->method('getAuthIdentifier')->willReturn($id);
		$user->method('getAuthIdentifiers')->willReturn([
			AuthUserInterface::IDENTIFIER_TYPE_EMAIL => $email,
			AuthUserInterface::IDENTIFIER_TYPE_PHONE => $phone,
		]);

		$store = AuthUserDataStore::getInstance($user, []);

		if ($has_totp_secret) {
			$store->set2FATotpSecret(TOTP::generateSecret());
		}

		if (null !== $preferred_method) {
			$store->set2FAMethod($preferred_method);
		}

		$user->method('getAuthUserDataStore')->willReturn($store);

		return $user;
	}
}
