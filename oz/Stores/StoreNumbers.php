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

namespace OZONE\Core\Stores;

use OZONE\Core\Exceptions\RuntimeException;

/**
 * What every store's `add()` computes: a number plus a number, a whole one staying whole.
 */
final class StoreNumbers
{
	/** How many times a store tries its compare-and-set before it gives up. */
	public const MAX_ATTEMPTS = 100;

	/**
	 * @throws RuntimeException when the stored value is not a number
	 */
	public static function sum(string $key, mixed $current, float|int $by): float|int
	{
		if (!\is_int($current) && !\is_float($current)) {
			throw new RuntimeException(\sprintf('The store entry "%s" is not a number: nothing is added to it.', $key));
		}

		return $current + $by;
	}

	/**
	 * @throws RuntimeException after too many concurrent writes to one entry
	 */
	public static function contended(string $key): RuntimeException
	{
		return new RuntimeException(\sprintf(
			'The store entry "%s" kept changing under %d attempts to add to it.',
			$key,
			self::MAX_ATTEMPTS
		));
	}
}
