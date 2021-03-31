<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Event;

final class Event
{
	/** @var bool */
	private $stopped = false;

	/** @var string */
	private $name;

	/** @var array */
	private $params;

	/** @var null|object|string */
	private $context;

	/**
	 * Event constructor.
	 *
	 * @param string $name
	 * @param mixed  $context
	 * @param array  $params
	 */
	public function __construct($name, $context = null, array $params = [])
	{
		$this->name    = $name;
		$this->params  = $params;
		$this->context = $context;
	}

	/**
	 * Gets event name
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Gets context from which event was triggered
	 *
	 * @return mixed
	 */
	public function getContext()
	{
		return $this->context;
	}

	/**
	 * Gets parameters passed to the event
	 *
	 * @return array
	 */
	public function getParams()
	{
		return $this->params;
	}

	/**
	 * Gets a single parameter by name
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function getParam($name)
	{
		if (isset($this->params[$name])) {
			return $this->params[$name];
		}

		return null;
	}

	/**
	 * Indicate whether or not to stop propagating this event
	 *
	 * @param bool $flag
	 */
	public function stopPropagation($flag)
	{
		$this->stopped = (bool) $flag;
	}

	/**
	 * Has this event indicated event propagation should stop?
	 *
	 * @return bool
	 */
	public function isPropagationStopped()
	{
		return $this->stopped;
	}
}
