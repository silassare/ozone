<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Http;



class Collection implements \ArrayAccess, \Countable, \IteratorAggregate
{
	/**
	 * The source data
	 *
	 * @var array
	 */
	protected $data = [];

	/**
	 * Creates new collection
	 *
	 * @param array $items Pre-populate collection with this key-value array
	 */
	public function __construct(array $items = [])
	{
		$this->replace($items);
	}

	/**
	 * Sets collection item
	 *
	 * @param string $key   The data key
	 * @param mixed  $value The data value
	 */
	public function set($key, $value)
	{
		$this->data[$key] = $value;
	}

	/**
	 * Gets collection item for key
	 *
	 * @param string $key     The data key
	 * @param mixed  $default The default value to return if data key does not exist
	 *
	 * @return mixed The key's value, or the default value
	 */
	public function get($key, $default = null)
	{
		return $this->has($key) ? $this->data[$key] : $default;
	}

	/**
	 * Adds item to collection, replacing existing items with the same data key
	 *
	 * @param array $items Key-value array of data to append to this collection
	 */
	public function replace(array $items)
	{
		foreach ($items as $key => $value) {
			$this->set($key, $value);
		}
	}

	/**
	 * Gets all items in collection
	 *
	 * @return array The collection's source data
	 */
	public function all()
	{
		return $this->data;
	}

	/**
	 * Gets collection keys
	 *
	 * @return array The collection's source data keys
	 */
	public function keys()
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
	public function has($key)
	{
		return \array_key_exists($key, $this->data);
	}

	/**
	 * Removes item from collection
	 *
	 * @param string $key The data key
	 */
	public function remove($key)
	{
		unset($this->data[$key]);
	}

	/**
	 * Removes all items from collection
	 */
	public function clear()
	{
		$this->data = [];
	}

	/**
	 * Does this collection have a given key?
	 *
	 * @param string $key The data key
	 *
	 * @return bool
	 *
	 * @see \ArrayAccess::offsetExists()
	 */
	public function offsetExists($key)
	{
		return $this->has($key);
	}

	/**
	 * Gets collection item for key
	 *
	 * @param string $key The data key
	 *
	 * @return mixed The key's value, or the default value
	 *
	 * @see \ArrayAccess::offsetGet()
	 */
	public function offsetGet($key)
	{
		return $this->get($key);
	}

	/**
	 * Sets collection item
	 *
	 * @param string $key   The data key
	 * @param mixed  $value The data value
	 *
	 * @see \ArrayAccess::offsetSet()
	 */
	public function offsetSet($key, $value)
	{
		$this->set($key, $value);
	}

	/**
	 * Removes item from collection
	 *
	 * @param string $key The data key
	 *
	 * @see \ArrayAccess::offsetUnset()
	 */
	public function offsetUnset($key)
	{
		$this->remove($key);
	}

	/**
	 * Gets number of items in collection
	 *
	 * @return int
	 *
	 * @see \Countable::count()
	 */
	public function count()
	{
		return \count($this->data);
	}

	/**
	 * Gets collection iterator
	 *
	 * @return \ArrayIterator
	 *
	 * @see \IteratorAggregate::getIterator()
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->data);
	}
}
