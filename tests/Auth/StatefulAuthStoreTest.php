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

use OZONE\Core\App\Keys;
use OZONE\Core\Auth\StatefulAuthenticationMethodStore;
use PHPUnit\Framework\TestCase;

/**
 * Tests the in-memory state managed by StatefulAuthenticationMethodStore.
 *
 * The store is the backing data-store for session-based auth methods.
 * It tracks 2FA pending users, verified flags, and previous-user info.
 * These tests exercise the public accessors for that state without
 * requiring a live HTTP session or database rows.
 *
 * @internal
 *
 * @coversNothing
 */
final class StatefulAuthStoreTest extends TestCase
{
	// -----------------------------------------------------------------------
	// 2FA pending-user flag
	// -----------------------------------------------------------------------

	public function testGet2FAPendingUserReturnsNullByDefault(): void
	{
		$store = $this->makeStore();
		// No DB user record exists for the null selector, so the method returns null.
		self::assertNull($store->get2FAPendingUser());
	}

	public function testClear2FAPendingUserMakesGetReturnNull(): void
	{
		$store = $this->makeStore();
		// Manually set a non-array value to simulate a stale entry,
		// then clear it and verify null is returned.
		$store->set('oz.2fa_pending_user', ['type' => 'user', 'id' => '999999999']);
		$store->clear2FAPendingUser();

		self::assertNull($store->get2FAPendingUser());
	}

	// -----------------------------------------------------------------------
	// Generic key/value store behaviour (security-relevant: no cross-leakage)
	// -----------------------------------------------------------------------

	public function testTwoDistinctStoreInstancesHaveIsolatedState(): void
	{
		$storeA = $this->makeStore();
		$storeB = $this->makeStore();

		$storeA->set('oz.2fa.verified', true);

		// storeB is a different state ID, so it must not see storeA's data.
		self::assertNull($storeB->get('oz.2fa.verified'));
	}

	public function testTwoCallsWithSameStateIdBothReceiveInitialData(): void
	{
		// Each getInstance() call constructs a new Store from the supplied data array.
		// State sharing across calls is handled by the Session layer (the session loads
		// the raw data array from DB and passes it to getInstance() each time).
		//
		// Note: Store uses dot-notation for get()/set(), so a key with dots is treated as
		// a nested path.  Use a plain key without dots to avoid ambiguity here.
		$id     = Keys::id64('same');
		$storeA = StatefulAuthenticationMethodStore::getInstance($id, ['initialized' => true]);
		$storeB = StatefulAuthenticationMethodStore::getInstance($id, ['initialized' => true]);

		// Both stores are initialised from the same data array, so they carry
		// the same logical state even though they may be different objects.
		self::assertTrue($storeA->get('initialized'));
		self::assertTrue($storeB->get('initialized'));
	}

	public function testSetAndGetRoundTrip(): void
	{
		$store = $this->makeStore();
		$store->set('oz.test.key', 'hello');
		self::assertSame('hello', $store->get('oz.test.key'));
	}

	public function testSetBooleanFlag(): void
	{
		$store = $this->makeStore();
		$store->set('oz.2fa.verified', true);
		self::assertTrue($store->get('oz.2fa.verified'));
	}

	public function testRemoveDeletesKey(): void
	{
		$store = $this->makeStore();
		$store->set('oz.some.key', 'value');
		$store->remove('oz.some.key');
		self::assertNull($store->get('oz.some.key'));
	}

	/** Each test gets a fresh store keyed by a unique state ID. */
	private function makeStore(): StatefulAuthenticationMethodStore
	{
		return StatefulAuthenticationMethodStore::getInstance(Keys::id64('test'), []);
	}
}
