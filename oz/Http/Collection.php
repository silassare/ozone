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

namespace OZONE\OZ\Http;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * Class Collection.
 */
class Collection implements ArrayAccess, Countable, IteratorAggregate
{
	/**
	 * The source data.
	 */
	protected array $data = [];

	/**
	 * Creates new collection.
	 *
	 * @param array $items Pre-populate collection with this key-value array
	 */
	public function __construct(array $items = [])
	{
		$this->replace($items);
	}

	/**
	 * Sets collection item.
	 *
	 * @param string $key   The data key
	 * @param mixed  $value The data value
	 */
	public function set(string $key, mixed $value): void
	{
		$this->data[$key] = $value;
	}

	/**
	 * Gets collection item for key.
	 *
	 * @param string     $key     The data key
	 * @param null|mixed $default The default value to return if data key does not exist
	 *
	 * @return mixed The key's value, or the default value
	 */
	public function get(string $key, mixed $default = null): mixed
	{
		return $this->has($key) ? $this->data[$key] : $default;
	}

	/**
	 * Adds item to collection, replacing existing items with the same data key.
	 *
	 * @param array $items Key-value array of data to append to this collection
	 */
	public function replace(array $items): void
	{
		foreach ($items as $key => $value) {
			$this->set($key, $value);
		}
	}

	/**
	 * Gets all items in collection.
	 *
	 * @return array The collection's source data
	 */
	public function all(): array
	{
		return $this->data;
	}

	/**
	 * Gets collection keys.
	 *
	 * @return array The collection's source data keys
	 */
	public function keys(): array
	{
		return \array_keys($this->data);
	}

	/**
	 * Does this collection have a given key?
	 *
	 * @param string $key The data key
	 *
	 * @return bool
	 */
	public function has(string $key): bool
	{
		return \array_key_exists($key, $this->data);
	}

	/**
	 * Removes item from collection.
	 *
	 * @param string $key The data key
	 */
	public function remove(string $key): void
	{
		unset($this->data[$key]);
	}

	/**
	 * Removes all items from collection.
	 */
	public function clear(): void
	{
		$this->data = [];
	}

	/**
	 * {@inheritDoc}
	 */
	public function offsetExists($offset): bool
	{
		return $this->has($offset);
	}

	/**
	 * {@inheritDoc}
	 */
	public function offsetGet($offset): mixed
	{
		return $this->get($offset);
	}

	/**
	 * {@inheritDoc}
	 */
	public function offsetSet($offset, $value): void
	{
		$this->set($offset, $value);
	}

	/**
	 * {@inheritDoc}
	 */
	public function offsetUnset($offset): void
	{
		$this->remove($offset);
	}

	/**
	 * {@inheritDoc}
	 */
	public function count(): int
	{
		return \count($this->data);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getIterator(): ArrayIterator
	{
		return new ArrayIterator($this->data);
	}
}
