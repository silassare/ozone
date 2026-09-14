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

use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Stores\Interfaces\StoreDriverInterface;

/**
 * Class StateRegistry.
 *
 * Durable key-value state, as opposed to cache.
 *
 * The two are the same machinery and a very different promise. Losing a cache entry costs a
 * recomputation; losing a state entry is visible to a user -- a half-finished wizard, a rate limit
 * that reset, a replay window that re-opened. A call site says which it is by the registry it asks:
 * {@see CacheRegistry} for caches, this one for state.
 *
 * A store is declared in `oz.stores.state`, and the driver it names has to promise
 * {@see StoreCapabilities::$durable}. That refusal is the point: it makes pointing
 * a form session at `.ozone/cache/` impossible rather than merely discouraged, since `.ozone/` is per
 * instance and may be deleted at any time.
 */
final class StateRegistry
{
	/**
	 * The settings group declaring the state stores.
	 */
	public const SETTINGS_GROUP = 'oz.stores.state';

	/**
	 * The default driver of a state store that names none.
	 */
	public const DEFAULT_DRIVER_SETTING = 'OZ_STATE_DEFAULT';

	/** @var array<string, KeyValueStore> */
	private static array $stores = [];

	/**
	 * Returns a named state store.
	 *
	 * @param string $name the store name, a key of `oz.stores.state`
	 *
	 * @return KeyValueStore
	 *
	 * @throws RuntimeException when the store is unknown, or its driver is not durable
	 */
	public static function store(string $name): KeyValueStore
	{
		if (isset(self::$stores[$name])) {
			return self::$stores[$name];
		}

		$config = Settings::load(self::SETTINGS_GROUP);

		if (!isset($config[$name])) {
			throw new RuntimeException(\sprintf(
				'Unknown state store "%s". Declare it in "%s", or use CacheRegistry::store() if'
					. ' losing its entries is acceptable.',
				$name,
				self::SETTINGS_GROUP
			));
		}

		$entry   = \is_array($config[$name]) ? $config[$name] : [];
		$driver  = $entry['driver'] ?? Settings::get('oz.stores', self::DEFAULT_DRIVER_SETTING);
		$options = $entry['options'] ?? [];

		if (!\is_string($driver) || !\is_subclass_of($driver, StoreDriverInterface::class)) {
			throw new RuntimeException(\sprintf(
				'The driver of the state store "%s" must implement "%s".',
				$name,
				StoreDriverInterface::class
			));
		}

		$provider = $driver::fromConfig($name, \is_array($options) ? $options : []);

		if (!$provider->capabilities()->durable) {
			throw new RuntimeException(\sprintf(
				'The driver "%s" cannot back the state store "%s": it does not promise durability.'
					. ' Losing an entry of a state store is visible to a user, so it needs a database,'
					. ' Redis, or FileStore rooted under data/state (options: {"root": "state"}).',
				$driver,
				$name
			));
		}

		return self::$stores[$name] = new KeyValueStore($name, $provider);
	}

	/**
	 * The names of every declared state store.
	 *
	 * @return list<string>
	 */
	public static function names(): array
	{
		/** @var list<string> $names */
		return \array_keys(Settings::load(self::SETTINGS_GROUP));
	}
}
