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

namespace OZONE\Core\Cache;

/**
 * Class CacheCapabilities.
 *
 * Describes the capabilities of a cache driver instance.
 */
final class CacheCapabilities
{
	/**
	 * CacheCapabilities constructor.
	 *
	 * @param bool $perEntryTTL     whether the driver supports per-entry TTL
	 * @param bool $persistent      whether the driver data survives process restart
	 * @param bool $expiryCallbacks whether the driver supports server-side expiry scanning (used by GC)
	 * @param bool $atomic          whether increment/decrement operations are atomic
	 */
	public function __construct(
		public readonly bool $perEntryTTL,
		public readonly bool $persistent,
		public readonly bool $expiryCallbacks,
		public readonly bool $atomic,
	) {}
}
