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

namespace OZONE\Core\Cache\Drivers;

use Gobl\Exceptions\GoblException;
use Override;
use OZONE\Core\Db\OZDbStore;
use OZONE\Core\Db\OZDbStoresQuery;

/**
 * Class DbCache.
 */
final class DbCache extends RuntimeCache
{
	private ?OZDbStore $db_store;

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function getSharedInstance(?string $namespace = null): static
	{
		return new self($namespace);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws GoblException
	 */
	#[Override]
	protected function save(): bool
	{
		$this->db_store->setValue(\serialize(self::$cache_data[$this->namespace]))
			->save();

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	protected function load(): array
	{
		$ref = $this->getRef();
		$qb  = new OZDbStoresQuery();

		$found = $qb->whereGroupIs(self::class)
			->whereKeyIs($ref)
			->find(1)
			->fetchClass();

		if ($found) {
			$this->db_store = $found;
			$content        = $found->getValue();
			$value          = \unserialize($content, ['allowed_classes' => true]);
			if (\is_array($value)) {
				return $value;
			}
		} else {
			$this->db_store = new OZDbStore();
			$this->db_store->setGroup(self::class)
				->setKey($ref)
				->setLabel($this->namespace);
		}

		return [];
	}

	/**
	 * Gets the cache reference.
	 *
	 * @return string
	 */
	private function getRef(): string
	{
		return \md5($this->namespace);
	}
}
