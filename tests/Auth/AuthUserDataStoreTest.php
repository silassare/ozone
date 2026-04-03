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

use OZONE\Core\Auth\AuthUserDataStore;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests the 2FA-related methods of {@see AuthUserDataStore}.
 *
 * @internal
 *
 * @coversNothing
 */
final class AuthUserDataStoreTest extends TestCase
{
	private static int $userSeq = 0;

	// has2FAEnabled / set2FAEnabled ----------------------------------------

	public function testHas2FAEnabledDefaultsFalse(): void
	{
		$store = $this->makeStore();
		self::assertFalse($store->has2FAEnabled());
	}

	public function testSet2FAEnabledToTrueEnables2FA(): void
	{
		$store = $this->makeStore();
		$store->set2FAEnabled(true);
		self::assertTrue($store->has2FAEnabled());
	}

	public function testSet2FAEnabledToFalseDisables2FA(): void
	{
		$store = $this->makeStore();
		$store->set2FAEnabled(true);
		$store->set2FAEnabled(false);
		self::assertFalse($store->has2FAEnabled());
	}

	public function testSet2FAEnabledDefaultArgumentIsTrue(): void
	{
		$store = $this->makeStore();
		$store->set2FAEnabled();
		self::assertTrue($store->has2FAEnabled());
	}

	public function testSet2FAEnabledReturnsFluentInstance(): void
	{
		$store = $this->makeStore();
		self::assertSame($store, $store->set2FAEnabled(true));
	}

	// get2FAMethod / set2FAMethod ------------------------------------------

	public function testGet2FAMethodDefaultsNull(): void
	{
		$store = $this->makeStore();
		self::assertNull($store->get2FAMethod());
	}

	public function testSet2FAMethodStoresChannelName(): void
	{
		$store = $this->makeStore();
		$store->set2FAMethod('totp');
		self::assertSame('totp', $store->get2FAMethod());
	}

	public function testSet2FAMethodToNullClearsPreference(): void
	{
		$store = $this->makeStore();
		$store->set2FAMethod('email');
		$store->set2FAMethod(null);
		self::assertNull($store->get2FAMethod());
	}

	public function testGet2FAMethodTreatsEmptyStringAsNull(): void
	{
		// Stores with a legacy empty-string value should also return null.
		$store = $this->makeStore(['2fa.method' => '']);
		self::assertNull($store->get2FAMethod());
	}

	public function testSet2FAMethodReturnsFluentInstance(): void
	{
		$store = $this->makeStore();
		self::assertSame($store, $store->set2FAMethod('sms'));
	}

	// get2FATotpSecret / set2FATotpSecret ----------------------------------

	public function testGet2FATotpSecretDefaultsNull(): void
	{
		$store = $this->makeStore();
		self::assertNull($store->get2FATotpSecret());
	}

	public function testSet2FATotpSecretStoresSecret(): void
	{
		$store  = $this->makeStore();
		$secret = 'ABCDEFGHIJKLMNOP';
		$store->set2FATotpSecret($secret);
		self::assertSame($secret, $store->get2FATotpSecret());
	}

	public function testSet2FATotpSecretToNullClearsSecret(): void
	{
		$store = $this->makeStore();
		$store->set2FATotpSecret('ABCDEFGHIJKLMNOP');
		$store->set2FATotpSecret(null);
		self::assertNull($store->get2FATotpSecret());
	}

	public function testGet2FATotpSecretTreatsEmptyStringAsNull(): void
	{
		$store = $this->makeStore(['2fa.totp_secret' => '']);
		self::assertNull($store->get2FATotpSecret());
	}

	public function testSet2FATotpSecretReturnsFluentInstance(): void
	{
		$store = $this->makeStore();
		self::assertSame($store, $store->set2FATotpSecret('ABCDEFGHIJKLMNOP'));
	}

	// combined 2FA state ---------------------------------------------------

	public function testIndependentFieldsDoNotInterfereWithEachOther(): void
	{
		$store = $this->makeStore();
		$store->set2FAEnabled(true);
		$store->set2FAMethod('totp');
		$store->set2FATotpSecret('SECRETVALUE12345');

		self::assertTrue($store->has2FAEnabled());
		self::assertSame('totp', $store->get2FAMethod());
		self::assertSame('SECRETVALUE12345', $store->get2FATotpSecret());
	}

	/**
	 * Creates a fresh AuthUserDataStore for an anonymous test user.
	 *
	 * Each call uses a unique user ID so that the runtime cache never returns
	 * a previously-created instance.
	 */
	private function makeStore(array $initial_data = []): AuthUserDataStore
	{
		$id   = 'test-user-' . ++self::$userSeq;
		$user = $this->createMock(AuthUserInterface::class);
		$user->method('getAuthUserType')->willReturn('test');
		$user->method('getAuthIdentifier')->willReturn($id);

		return AuthUserDataStore::getInstance($user, $initial_data);
	}
}
