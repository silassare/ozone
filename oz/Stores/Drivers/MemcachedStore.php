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

namespace OZONE\Core\Stores\Drivers;

use Memcached;
use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Stores\Interfaces\StoreDriverInterface;
use OZONE\Core\Stores\StoreCapabilities;
use OZONE\Core\Stores\StoreEntry;
use OZONE\Core\Stores\StoreNumbers;
use OZONE\Core\Utils\Hasher;
use RuntimeException;

/**
 * Class MemcachedStore.
 *
 * The `ext-memcached` check is in {@see self::fromConfig()}, not at the top of this file: a
 * file-level `throw` fires while the class is being **autoloaded**, so merely naming the class --
 * `MemcachedStore::class` in a settings array, an `is_subclass_of()` check, a reflection pass --
 * would blow up on a host without the extension, long before anyone asked for a Memcached store.
 */
final class MemcachedStore implements StoreDriverInterface
{
	/**
	 * The 30 days above which Memcached reads an expiry as an absolute time rather than a delay.
	 */
	public const int RELATIVE_LIMIT = 2_592_000;

	/**
	 * The pool a store connects to when nothing names one: Memcached's own default address.
	 */
	public const string DEFAULT_SERVERS = '127.0.0.1:11211';

	/**
	 * The port an entry of the pool leaves out.
	 */
	public const int DEFAULT_PORT = 11211;

	/**
	 * MemcachedStore constructor.
	 */
	private function __construct(private Memcached $memcached) {}

	#[Override]
	public function capabilities(): StoreCapabilities
	{
		return new StoreCapabilities(
			perEntryTTL: true,
			persistent: true,
			expiryCallbacks: false,
			atomic: true,
			// Memcached evicts under pressure: never durable.
			durable: false,
		);
	}

	#[Override]
	public function get(string $key): ?StoreEntry
	{
		$value = $this->memcached->get($key);

		if (Memcached::RES_NOTFOUND === $this->memcached->getResultCode()) {
			return null;
		}

		$expire = $this->memcached->get($key . ':expire');

		if (Memcached::RES_NOTFOUND === $this->memcached->getResultCode()) {
			return new StoreEntry($key, $value);
		}

		if ($expire > \microtime(true)) {
			return new StoreEntry($key, $value, (float) $expire);
		}

		return null;
	}

	#[Override]
	public function getMultiple(array $keys): array
	{
		$items = [];

		foreach ($keys as $key) {
			$item = $this->get($key);

			if (null !== $item) {
				$items[$key] = $item;
			}
		}

		return $items;
	}

	#[Override]
	public function set(StoreEntry $entry): bool
	{
		$key        = $entry->key;
		$expire     = $entry->expiresAt;
		$expiration = (null !== $expire) ? self::expiration($expire) : 0;

		$this->memcached->set($key, $entry->value, $expiration);

		if (Memcached::RES_SUCCESS !== $this->memcached->getResultCode()) {
			return false;
		}

		if (null !== $expire) {
			$this->memcached->set($key . ':expire', $expire, $expiration);
		}

		return Memcached::RES_SUCCESS === $this->memcached->getResultCode();
	}

	/**
	 * What Memcached is given for an entry that expires at the absolute time it carries.
	 *
	 * Seconds, and never milliseconds: an entry's expiry is an absolute microtime, and handing
	 * Memcached a thousand times that number made every entry one with a timestamp tens of
	 * millennia away, so nothing this store wrote ever expired of its own accord.
	 *
	 * Memcached reads the number two ways: above 30 days it is the absolute time the entry ends at,
	 * at or below it the seconds it has left, and 0 means keep it for ever. So a short life is sent
	 * as the seconds it has left, which no clock difference between this machine and the server can
	 * stretch, and a longer one -- or a life already over, which 0 would turn into no expiry at all
	 * -- as the time it ends at, the only form that cannot be read as the other.
	 *
	 * Public so that the rule can be tested without a Memcached server, which the suite has none of.
	 *
	 * @internal
	 */
	public static function expiration(float $expires_at): int
	{
		$seconds_left = (int) \ceil($expires_at - \microtime(true));

		return ($seconds_left > 0 && $seconds_left <= self::RELATIVE_LIMIT)
			? $seconds_left
			: (int) \ceil($expires_at);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Compare-and-set (`gets` / `cas`), read again when another client wrote first; a missing entry
	 * is created with `add`, which only one client wins.
	 */
	#[Override]
	public function add(string $key, float|int $by, bool $create = false, ?float $expiresAt = null): float|int|null
	{
		for ($attempt = 0; $attempt < StoreNumbers::MAX_ATTEMPTS; ++$attempt) {
			$got = $this->memcached->get($key, null, Memcached::GET_EXTENDED);

			if (!\is_array($got)) {
				if (!$create) {
					return null;
				}

				// Memcached takes an expiry in seconds, absolute here.
				$expiration = null !== $expiresAt ? (int) \ceil($expiresAt) : 0;

				if ($this->memcached->add($key, $by, $expiration)) {
					if (null !== $expiresAt) {
						$this->memcached->set($key . ':expire', $expiresAt, $expiration);
					}

					return $by;
				}

				continue;
			}

			$sum = StoreNumbers::sum($key, $got['value'] ?? null, $by);

			if (0 === $by) {
				return $sum;
			}

			$expire     = $this->memcached->get($key . ':expire');
			$expiration = \is_numeric($expire) ? (int) \ceil((float) $expire) : 0;

			if ($this->memcached->cas((float) ($got['cas'] ?? 0), $key, $sum, $expiration)) {
				return $sum;
			}
		}

		throw StoreNumbers::contended($key);
	}

	#[Override]
	public function delete(string $key): bool
	{
		return $this->memcached->delete($key);
	}

	#[Override]
	public function deleteMultiple(array $keys): bool
	{
		$deleted = true;

		foreach ($keys as $key) {
			$deleted = $this->delete($key) && $deleted;
		}

		return $deleted;
	}

	#[Override]
	public function clear(): bool
	{
		return $this->memcached->flush();
	}

	/**
	 * {@inheritDoc}
	 *
	 * MemcachedStore does not support server-side expiry scanning.
	 */
	#[Override]
	public function getExpiredEntries(int $limit = 100): array
	{
		return [];
	}

	#[Override]
	public static function fromConfig(string $namespace, array $options = []): static
	{
		self::assertExtension();

		static $memcached_instances = [];

		$servers = self::servers($options);

		if (isset($memcached_instances[$namespace])) {
			$instance = $memcached_instances[$namespace];
		} else {
			$instance = new Memcached($namespace);
			// Add servers if none connected yet.
			if (!\count($instance->getServerList())) {
				$prefix = Hasher::shorten($namespace);
				$instance->setOption(Memcached::OPT_PREFIX_KEY, $prefix . ':');
				$instance->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
				$instance->setOption(Memcached::OPT_RECV_TIMEOUT, 1000);
				$instance->setOption(Memcached::OPT_SEND_TIMEOUT, 3000);
				$instance->setOption(Memcached::OPT_TCP_NODELAY, true);
				$instance->addServers($servers);
			}

			$memcached_instances[$namespace] = $instance;
		}

		return new self($instance);
	}

	/**
	 * The pool a store connects to, as `Memcached::addServers()` takes it.
	 *
	 * Nothing was read for it before: the list handed to `addServers()` was the literal
	 * `['_' => []]`, which names no server at all, so a Memcached store could reach none and the
	 * `$options` of its settings entry went nowhere. A store's own `servers` option is read first,
	 * then `oz.memcached`.
	 *
	 * @param array<string, mixed> $options the store's options, from its settings entry
	 *
	 * @return list<array{0: string, 1: int}>
	 */
	private static function servers(array $options): array
	{
		$declared = $options['servers']
			?? Settings::get('oz.memcached', 'OZ_MEMCACHED_SERVERS', self::DEFAULT_SERVERS);

		if (\is_string($declared)) {
			$declared = \explode(',', $declared);
		}

		$servers = [];

		foreach ((array) $declared as $entry) {
			if (\is_array($entry)) {
				$host = \trim((string) ($entry[0] ?? ''));
				$port = (int) ($entry[1] ?? self::DEFAULT_PORT);
			} else {
				// `host`, or `host:port`: the last colon, so an IPv6 address keeps its own.
				$entry = \trim((string) $entry);
				$colon = \strrpos($entry, ':');
				$host  = false === $colon ? $entry : \substr($entry, 0, $colon);
				$port  = false === $colon ? self::DEFAULT_PORT : (int) \substr($entry, $colon + 1);
			}

			if ('' !== $host) {
				$servers[] = [$host, $port < 1 ? self::DEFAULT_PORT : $port];
			}
		}

		if (empty($servers)) {
			throw new RuntimeException(
				'No Memcached server is configured: see "OZ_MEMCACHED_SERVERS" in "oz.memcached".'
			);
		}

		return $servers;
	}

	/**
	 * Fails when `ext-memcached` is missing.
	 */
	private static function assertExtension(): void
	{
		/** @noinspection ClassConstantCanBeUsedInspection */
		if (!\class_exists('\Memcached')) {
			throw new RuntimeException(
				'The Memcached cache driver needs the PHP ext-memcached extension, which is not installed.'
			);
		}
	}
}
