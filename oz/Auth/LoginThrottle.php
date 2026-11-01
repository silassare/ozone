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
	public const string CACHE_NAMESPACE = 'oz:rate_limit';

	/**
	 * Claims one attempt for the subject: counts it, and answers whether it may go ahead.
	 *
	 * The check and the record in one step, which is what makes a maximum a maximum. Read first and
	 * recorded afterwards, attempts that arrive together all read the same count before any of them
	 * has been recorded, so a burst of a hundred got a hundred tries whatever the maximum was: the
	 * limit held per round of attempts rather than over the window, and concurrency alone multiplied
	 * what an attacker was allowed. The store counts and answers the total it reached ({@see
	 * KeyValueStore::count()}, one atomic step per driver), so each attempt of a burst gets a number
	 * of its own and only those at or below the maximum are let through.
	 *
	 * A right password clears the subject ({@see self::clear()}), so what accumulates between two
	 * successes is the attempts that failed.
	 *
	 * A store too contended to count raises rather than answering, which refuses the attempt: a
	 * burst is the shape of the attack this guards against, and letting one through because the
	 * counter could not keep up is the one outcome worse than an error.
	 */
	public static function claim(string $subject): bool
	{
		$max = self::maxFailures();

		if ($max <= 0) {
			return true;
		}

		return self::store()->count(self::key($subject), 1, self::window()) <= $max;
	}

	/**
	 * Gives back an attempt claimed for the subject, because it turned out not to be one.
	 *
	 * For a step of the protocol rather than a guess at a credential -- a digest nonce of ours that
	 * aged out and is answered with a fresh challenge -- which has to be claimed before it can be
	 * told apart from an attempt that is. Having nothing to give back is not an error: the window
	 * may have ended in between, and the count is never created here.
	 */
	public static function release(string $subject): void
	{
		self::store()->decrement(self::key($subject));
	}

	/**
	 * Whether the subject reached the maximum number of failures in its window.
	 *
	 * A reading, for a caller that wants to know. A caller deciding whether to let an attempt go
	 * ahead uses {@see self::claim()}: asking here and recording afterwards leaves the gap between
	 * the two open.
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
	 *
	 * For a flow that learns of a failure somewhere other than where it let the attempt through -- a
	 * second factor that was wrong, a signature checked later. A flow that decides whether an
	 * attempt may go ahead claims it instead ({@see self::claim()}), which counts it in the same
	 * step as the decision.
	 */
	public static function recordFailure(string $subject): void
	{
		$store = self::store();
		$key   = self::key($subject);

		// The window runs from the first failure: counting keeps its expiry. One atomic step, so
		// failures arriving together are all counted.
		$store->count($key, 1, self::window());
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
