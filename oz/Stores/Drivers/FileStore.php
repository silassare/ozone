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
			atomic: false,
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
