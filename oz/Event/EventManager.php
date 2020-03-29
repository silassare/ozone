<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Event;

\defined('OZ_SELF_SECURITY_CHECK') || die;

final class EventManager
{
	/** @var array */
	private static $listeners = [];

	/** @var \OZONE\OZ\Event\EventManager */
	private static $instance;

	/**
	 * EventManager constructor. (Singleton pattern)
	 */
	private function __construct()
	{
	}

	/**
	 * Attaches a listener to an event
	 *
	 * @param string   $event    the event to attach too
	 * @param callable $callback a callable function
	 * @param int      $priority the priority at which the $callback executed
	 *
	 * @return bool true on success false on failure
	 */
	public function attach($event, $callback, $priority = 0)
	{
		self::$listeners[$event][$priority][] = $callback;

		return true;
	}

	/**
	 * Detaches a listener from an event
	 *
	 * @param string   $event    the event to attach too
	 * @param callable $callback a callable function
	 *
	 * @return bool true on success false on failure
	 */
	public function detach($event, $callback)
	{
		$success = false;

		if (isset(self::$listeners[$event])) {
			foreach (self::$listeners[$event] as $priority => $listeners) {
				foreach ($listeners as $index => $listener) {
					if ($listener === $callback) {
						$success = true;
						unset(self::$listeners[$event][$priority][$index]);
					}
				}
			}
		}

		return $success;
	}

	/**
	 * Clear all listeners for a given event
	 *
	 * @param string $event
	 *
	 * @return $this
	 */
	public function clearListeners($event)
	{
		if (isset(self::$listeners[$event])) {
			unset(self::$listeners[$event]);
		}

		return $this;
	}

	/**
	 * Trigger an event
	 *
	 * Can accept an event object or will create one if not passed
	 *
	 * @param \OZONE\OZ\Event\Event|string $event
	 * @param mixed                        $context
	 * @param array                        $params
	 *
	 * @return $this
	 */
	public function trigger($event, $context = null, array $params = [])
	{
		if (!($event instanceof Event)) {
			$event = new Event($event, $context, $params);
		}

		$name = $event->getName();

		if (isset(self::$listeners[$name])) {
			foreach (self::$listeners[$name] as $priority => $listeners) {
				foreach ($listeners as $index => $listener) {
					if (!$event->isPropagationStopped()) {
						\call_user_func_array($listener, [$event]);
					}
				}
			}
		}

		return $this;
	}

	/**
	 * Gets event manager instance. (Singleton pattern)
	 *
	 * @return \OZONE\OZ\Event\EventManager
	 */
	public static function getInstance()
	{
		if (!isset(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Prevent external clone. (Singleton pattern)
	 */
	private function __clone()
	{
	}
}
