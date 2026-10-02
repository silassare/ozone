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

use Override;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Crypt\SignedSerializer;
use OZONE\Core\FS\FS;
use OZONE\Core\Stores\StoreCapabilities;

/**
 * Class FileStore.
 *
 * Files on the local disk, signed with the app secret.
 *
 * Where those files go decides whether this instance may back a **state** store: under
 * `.ozone/cache/` it is a cache, and `.ozone/` is per instance and may be deleted at any time; under
 * `data/state/{scope}` ({@see self::ROOT_STATE}) it is durable, and losing it is not allowed. Pass
 * `['root' => FileStore::ROOT_STATE]` in the store's `options` for the second.
 */
final class FileStore extends MemoryStore
{
	/**
	 * Files under `.ozone/cache/`: disposable, and the default.
	 */
	public const ROOT_CACHE = 'cache';

	/**
	 * Files under `data/state/{scope}`: durable, and required to back a state store.
	 */
	public const ROOT_STATE = 'state';

	private ?string $cache_path = null;

	/** @var array<string, null|int> by namespace: the inode of the file its entries were read from */
	private static array $inodes = [];

	/**
	 * @param string $namespace
	 * @param string $root      {@see self::ROOT_CACHE} or {@see self::ROOT_STATE}
	 */
	public function __construct(?string $namespace = null, private readonly string $root = self::ROOT_CACHE)
	{
		parent::__construct($namespace);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function capabilities(): StoreCapabilities
	{
		return new StoreCapabilities(
			perEntryTTL: true,
			persistent: true,
			expiryCallbacks: false,
			atomic: true,
			// Only when the files are in data/state: .ozone/cache may be deleted at any time.
			durable: self::ROOT_STATE === $this->root,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function fromConfig(string $namespace, array $options = []): static
	{
		return new self($namespace, self::ROOT_STATE === ($options['root'] ?? null)
			? self::ROOT_STATE
			: self::ROOT_CACHE);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Several processes share the file: the change is made under an exclusive lock, on what the file
	 * holds now, so a write of one never undoes another's, and an add never loses one.
	 */
	#[Override]
	protected function mutate(callable $change): mixed
	{
		$path = $this->getCachePath();
		$lock = \fopen($path . '.lock', 'c');

		if (false === $lock) {
			throw new RuntimeException(\sprintf('The store file "%s" cannot be locked.', $path));
		}

		try {
			\flock($lock, \LOCK_EX);

			self::$cache_data[$this->namespace] = $this->load();

			$result = $change();

			$this->save();
			$this->remember($path);

			return $result;
		} finally {
			\flock($lock, \LOCK_UN);
			\fclose($lock);
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * Another process may have written the file since it was read: each write replaces it whole
	 * (`writeAtomic()`), so a new inode says it changed, within the same second too.
	 */
	#[Override]
	protected function refresh(): void
	{
		$path = $this->getCachePath();

		\clearstatcache(true, $path);

		$inode = \is_file($path) ? (\fileinode($path) ?: null) : null;

		if ($inode !== (self::$inodes[$this->namespace] ?? null)) {
			self::$cache_data[$this->namespace] = $this->load();
			self::$inodes[$this->namespace]     = $inode;
		}
	}


	/**
	 * {@inheritDoc}
	 */
	#[Override]
	protected function save(): bool
	{
		$path = $this->getCachePath();

		// Atomic: under data/state these files are durable state, and the volume may be shared
		// between instances, so a reader must never catch a half-written entry.
		FS::fromRoot()->writeAtomic($path, SignedSerializer::serialize(self::$cache_data[$this->namespace]));

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	protected function load(): array
	{
		$path   = $this->getCachePath();
		$filter = FS::fromRoot()->filter();
		if ($filter->isFile()
			->check($path)
		) {
			$cache = \file_get_contents($path);

			if ($cache) {
				// An unsigned (legacy or tampered) file is ignored, then overwritten on next save.
				[$signed, $value] = SignedSerializer::unserialize($cache);

				if ($signed && \is_array($value)) {
					return $value;
				}
			}
		}

		return [];
	}

	/**
	 * Notes the file just written as the one the entries are from.
	 */
	private function remember(string $path): void
	{
		\clearstatcache(true, $path);

		self::$inodes[$this->namespace] = \is_file($path) ? (\fileinode($path) ?: null) : null;
	}

	/**
	 * Gets the cache path.
	 *
	 * @return string
	 */
	protected function getCachePath(): string
	{
		if (empty($this->cache_path)) {
			$hash = \md5($this->namespace);
			$dir1 = \substr($hash, 0, 2);
			$dir2 = \substr($hash, 2, 2);

			$fm = self::ROOT_STATE === $this->root
				? scope()->getStateStoreDir()->cd('php', true)
				: app()->getCacheDir()->cd('php_cache', true);

			$fm->cd($dir1, true)->cd($dir2, true);

			$this->cache_path = $fm->resolve($hash . '.cache');
		}

		return $this->cache_path;
	}
}
