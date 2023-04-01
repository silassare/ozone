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

namespace OZONE\OZ\Cache\Drivers;

use OZONE\OZ\FS\FilesManager;

/**
 * Class PHPCache.
 */
class PHPCache extends RuntimeCache
{
	private ?string $cache_path = null;

	/**
	 * {@inheritDoc}
	 */
	public static function getSharedInstance(?string $namespace = null): self
	{
		return new self($namespace);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function save(): bool
	{
		$path = $this->getCachePath();
		$fm   = new FilesManager();
		$fm->wf($path, \serialize(self::$cache_data[$this->namespace]));

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function load(): array
	{
		$path   = $this->getCachePath();
		$filter = (new FilesManager())->filter();
		if ($filter->isFile()
				   ->check($path)) {
			$cache = \file_get_contents($path);

			if ($cache) {
				$value = \unserialize($cache, ['allowed_classes' => true]);
				if (\is_array($value)) {
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

			$fm = new FilesManager(OZ_CACHE_DIR);
			$fm->cd('php_cache', true)
			   ->cd($dir1, true)
			   ->cd($dir2, true);

			$this->cache_path = $fm->resolve($hash . '.cache');
		}

		return $this->cache_path;
	}
}
