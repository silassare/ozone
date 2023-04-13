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

namespace OZONE\OZ\Cache;

use DateInterval;
use DateTime;

/**
 * Class CacheItem.
 *
 * @internal
 */
final class CacheItem
{
	/**
	 * @var string
	 */
	private string $key;

	/**
	 * @var mixed
	 */
	private mixed $value;

	/**
	 * @var null|float
	 */
	private ?float $expire;

	/**
	 * CacheItem constructor.
	 *
	 * @param string     $key    the key under which to store the value
	 * @param mixed      $value  the value to store
	 * @param null|float $expire the expiration time, defaults to null
	 */
	public function __construct(string $key, mixed $value, ?float $expire = null)
	{
		$this->key    = $key;
		$this->value  = $value;
		$this->expire = $expire;
	}

	/**
	 * Expire time getter.
	 *
	 * @return null|float
	 */
	public function getExpire(): ?float
	{
		return $this->expire;
	}

	/**
	 * Checks if this cache item has expired.
	 *
	 * @return bool
	 */
	public function expired(): bool
	{
		return null !== $this->expire && \microtime(true) > $this->expire;
	}

	/**
	 * Returns this cache item key.
	 *
	 * @return string
	 */
	public function getKey(): string
	{
		return $this->key;
	}

	/**
	 * Returns this cache item value.
	 *
	 * @return mixed
	 */
	public function get(): mixed
	{
		return $this->value;
	}

	/**
	 * Sets this cache item value.
	 *
	 * @param mixed $value
	 *
	 * @return $this
	 */
	public function set(mixed $value): self
	{
		$this->value = $value;

		return $this;
	}

	/**
	 * Sets cache item lifetime.
	 *
	 * @param null|DateInterval|float $lifetime
	 *
	 * @return $this
	 */
	public function expiresAfter(float|DateInterval|null $lifetime): self
	{
		if (null === $lifetime) {
			$this->expire = null;
		} elseif ($lifetime instanceof DateInterval) {
			$duration     = DateTime::createFromFormat('U', '0')
				->add($lifetime)
				->format('U.u');
			$this->expire = \microtime(true) + (float) $duration;
		} else {
			$this->expire = $lifetime + \microtime(true);
		}

		return $this;
	}
}
