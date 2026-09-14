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

namespace OZONE\Core\Auth;

use OZONE\Core\App\Settings;
use OZONE\Core\Stores\KeyValueStore;
use OZONE\Core\Stores\StateRegistry;
use OZONE\Core\Utils\Hasher;

/**
 * Class LoginThrottle.
 *
 * Counts failed password attempts per subject (an account, or the identifier
 * submitted for an unknown account) and locks the subject once
 * `OZ_AUTH_LOGIN_MAX_FAILURES` is reached. The window starts at the first failure
 * and lasts `OZ_AUTH_LOGIN_FAILURES_WINDOW` seconds; a successful attempt clears it.
 *
 * Unknown identifiers are counted too, so a locked, an unknown and an existing
 * account behave the same and failures reveal nothing.
 */
final class LoginThrottle
{
	public const CACHE_NAMESPACE = 'oz:rate_limit';

	/**
	 * Whether the subject reached the maximum number of failures in its window.
	 */
	public static function isLocked(string $subject): bool
	{
		$max = self::maxFailures();

		return $max > 0 && self::failures($subject) >= $max;
	}

	/**
	 * Number of failures recorded for the subject in its current window.
	 */
	public static function failures(string $subject): int
	{
		return (int) self::store()->get(self::key($subject), 0);
	}

	/**
	 * Records a failed attempt.
	 */
	public static function recordFailure(string $subject): void
	{
		$store = self::store();
		$key   = self::key($subject);

		// The window runs from the first failure: incrementing keeps its expiry.
		if (!$store->increment($key)) {
			$store->set($key, 1, self::window());
		}
	}

	/**
	 * Forgets the subject's failures.
	 */
	public static function clear(string $subject): void
	{
		self::store()->delete(self::key($subject));
	}

	private static function key(string $subject): string
	{
		return 'login:' . Hasher::hash32(\strtolower($subject));
	}

	private static function store(): KeyValueStore
	{
		return StateRegistry::store(self::CACHE_NAMESPACE);
	}

	private static function maxFailures(): int
	{
		return (int) Settings::get('oz.auth', 'OZ_AUTH_LOGIN_MAX_FAILURES', 5);
	}

	private static function window(): int
	{
		return (int) Settings::get('oz.auth', 'OZ_AUTH_LOGIN_FAILURES_WINDOW', 900);
	}
}
