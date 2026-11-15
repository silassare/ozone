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
	public const string SETTINGS_GROUP = 'oz.stores.state';

	/**
	 * The default driver of a state store that names none.
	 */
	public const string DEFAULT_DRIVER_SETTING = 'OZ_STATE_DEFAULT';

	/** @var array<string, KeyValueStore> */
	private static array $stores = [];

	/**
	 * Returns a named state store.
	 *
	 * @param string $name the store name, a key of `oz.stores.state`
	 *
	 * @throws RuntimeException when the store is unknown, or its driver is not durable
	 */
	public static function store(string $name): KeyValueStore
	{
		return self::resolve($name, $name);
	}

	/**
	 * A declared state store, one namespace per owner.
	 *
	 * What it is for: durable state that belongs to **somebody** rather than to the project -- one
	 * user's, one session's, one tenant's. The declaration stays one (a driver, its options, the
	 * durability it promises); what differs is whose entries these are, and that goes in the
	 * namespace rather than in the keys. Namespacing by key instead is what makes every read over
	 * the store somebody else's business too: clearing one owner's entries, listing them, reading
	 * what changed since a version -- each would need a prefix nobody can enforce, and the first one
	 * written without it would answer with another owner's rows.
	 *
	 * @param string $name  the store name, a key of `oz.stores.state`
	 * @param string $scope whose entries these are, as a string of the caller's own making
	 *
	 * @throws RuntimeException when the store is unknown, or its driver is not durable
	 */
	public static function scoped(string $name, string $scope): KeyValueStore
	{
		if ('' === \trim($scope)) {
			throw new RuntimeException(\sprintf(
				'A scoped state store needs a scope: "%s" was asked for with none, which is the'
					. ' store itself and not one owner of it.',
				$name
			));
		}

		return self::resolve($name, $name . ':' . $scope);
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

	/**
	 * The store a declaration describes, under the namespace asked for.
	 *
	 * @param string $declared  the store name, a key of `oz.stores.state`
	 * @param string $namespace what the driver is given: the name itself, or the name and a scope
	 *
	 * @throws RuntimeException when the store is unknown, or its driver is not durable
	 */
	private static function resolve(string $declared, string $namespace): KeyValueStore
	{
		if (isset(self::$stores[$namespace])) {
			return self::$stores[$namespace];
		}

		$config = Settings::load(self::SETTINGS_GROUP);

		if (!isset($config[$declared])) {
			throw new RuntimeException(\sprintf(
				'Unknown state store "%s". Declare it in "%s", or use CacheRegistry::store() if'
					. ' losing its entries is acceptable.',
				$declared,
				self::SETTINGS_GROUP
			));
		}

		$entry   = \is_array($config[$declared]) ? $config[$declared] : [];
		$driver  = $entry['driver'] ?? Settings::get('oz.stores', self::DEFAULT_DRIVER_SETTING);
		$options = $entry['options'] ?? [];

		if (!\is_string($driver) || !\is_subclass_of($driver, StoreDriverInterface::class)) {
			throw new RuntimeException(\sprintf(
				'The driver of the state store "%s" must implement "%s".',
				$declared,
				StoreDriverInterface::class
			));
		}

		$provider = $driver::fromConfig($namespace, \is_array($options) ? $options : []);

		if (!$provider->capabilities()->durable) {
			throw new RuntimeException(\sprintf(
				'The driver "%s" cannot back the state store "%s": it does not promise durability.'
					. ' Losing an entry of a state store is visible to a user, so it needs a database,'
					. ' Redis, or FileStore rooted under data/state (options: {"root": "state"}).',
				$driver,
				$declared
			));
		}

		return self::$stores[$namespace] = new KeyValueStore($namespace, $provider);
	}
}
