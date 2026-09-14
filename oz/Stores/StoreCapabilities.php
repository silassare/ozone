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

/**
 * Class StoreCapabilities.
 *
 * Describes the capabilities of a cache driver instance.
 *
 * `persistent` and `durable` are not the same question, and conflating them is what let durable
 * state end up in a cache. `persistent` asks whether the data survives the process; `durable` asks
 * whether losing it is *allowed*, which depends on where this instance was configured to write, not
 * on the driver class. `FileStore` is persistent either way, but only durable when it is rooted under
 * `data/state/{scope}` -- under `.ozone/cache/` it is a cache, and `.ozone/` may be deleted at any
 * time. A store declared in `oz.stores.state` refuses a driver that does not promise `durable`.
 */
final class StoreCapabilities
{
	/**
	 * StoreCapabilities constructor.
	 *
	 * @param bool $perEntryTTL     whether the driver supports per-entry TTL
	 * @param bool $persistent      whether the driver data survives process restart
	 * @param bool $expiryCallbacks whether the driver supports server-side expiry scanning (used by GC)
	 * @param bool $atomic          whether increment/decrement operations are atomic
	 * @param bool $durable         whether this *instance* may back a state store
	 */
	public function __construct(
		public readonly bool $perEntryTTL,
		public readonly bool $persistent,
		public readonly bool $expiryCallbacks,
		public readonly bool $atomic,
		public readonly bool $durable = false,
	) {}
}
