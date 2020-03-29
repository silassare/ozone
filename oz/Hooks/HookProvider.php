<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Hooks;

use InvalidArgumentException;
use OZONE\OZ\Hooks\Interfaces\HookProviderInterface;

\defined('OZ_SELF_SECURITY_CHECK') || die;

abstract class HookProvider implements HookProviderInterface
{
	/**
	 * @var \OZONE\OZ\Hooks\HookProvider[]
	 */
	protected static $instances = [];

	protected $hook_receivers = [
		self::RUN_FIRST   => [],
		self::RUN_DEFAULT => [],
		self::RUN_LAST    => [],
	];

	/**
	 * Gets a hook provider instance.
	 *
	 * @return $this
	 */
	public static function getInstance()
	{
		if (!isset(self::$instances[static::class])) {
			self::$instances[static::class] = new static();
		}

		return self::$instances[static::class];
	}

	/**
	 * Register a hook receiver class.
	 *
	 * @param string $hook_receiver_class
	 * @param int    $priority
	 *
	 * @return $this
	 */
	public static function registerHookReceiverClass($hook_receiver_class, $priority = self::RUN_DEFAULT)
	{
		if (!static::isCompatibleHookReceiverClass($hook_receiver_class)) {
			throw new InvalidArgumentException(\sprintf('"%s" is not a valid hook receiver class for "%s".', $hook_receiver_class, static::class));
		}

		return static::getInstance()
					 ->addHookReceiverClass($hook_receiver_class, $priority);
	}

	/**
	 * HookProvider constructor.
	 */
	protected function __construct()
	{
	}

	/**
	 * Add a hook receiver class.
	 *
	 * @param string $hook_receiver_class
	 * @param int    $priority
	 *
	 * @return $this
	 */
	protected function addHookReceiverClass($hook_receiver_class, $priority)
	{
		if (
			$priority === self::RUN_DEFAULT
			|| $priority === self::RUN_FIRST
			|| $priority === self::RUN_LAST
		) {
			$this->hook_receivers[$priority][] = [
				'type'  => 'class',
				'class' => $hook_receiver_class,
			];
		} else {
			throw new InvalidArgumentException(
				\sprintf(
					'Invalid priority "%s" set for hook receiver "%s". Allowed value are %s::RUN_* constants.',
					$priority,
					$hook_receiver_class,
					self::class
				)
			);
		}

		return $this;
	}

	/**
	 * Add a hook receiver callable.
	 *
	 * @param string   $hook
	 * @param callable $hook_receiver_callable
	 * @param int      $priority
	 *
	 * @return $this
	 */
	protected function addHookReceiverCallable($hook, callable $hook_receiver_callable, $priority)
	{
		if (
			$priority === self::RUN_DEFAULT
			|| $priority === self::RUN_FIRST
			|| $priority === self::RUN_LAST
		) {
			$this->hook_receivers[$priority][] = [
				'type'     => 'callable',
				'callable' => $hook_receiver_callable,
				'hook'     => $hook,
			];
		} else {
			throw new InvalidArgumentException(
				\sprintf(
					'Invalid priority "%s" set for hook receiver callable. Allowed value are %s::RUN_* constants.',
					$priority,
					self::class
				)
			);
		}

		return $this;
	}

	/**
	 * @param string   $hook
	 * @param callable $cb_class
	 * @param callable $cb_callable
	 *
	 * @return $this
	 */
	protected function loop($hook, callable $cb_class, callable $cb_callable)
	{
		$first   = $this->hook_receivers[self::RUN_FIRST];
		$default = $this->hook_receivers[self::RUN_DEFAULT];
		$last    = $this->hook_receivers[self::RUN_LAST];

		foreach ($first as $receiver) {
			if ($receiver['type'] === 'callable') {
				if ($receiver['hook'] === $hook) {
					\call_user_func($cb_callable, $receiver['callable']);
				}
			} else {
				$rc = $receiver['class'];
				\call_user_func($cb_class, static::getReceiverInstance($rc));
			}
		}

		foreach ($default as $receiver) {
			if ($receiver['type'] === 'callable') {
				if ($receiver['hook'] === $hook) {
					\call_user_func($cb_callable, $receiver['callable']);
				}
			} else {
				$rc = $receiver['class'];
				\call_user_func($cb_class, static::getReceiverInstance($rc));
			}
		}

		$last = \array_reverse($last);

		foreach ($last as $receiver) {
			if ($receiver['type'] === 'callable') {
				if ($receiver['hook'] === $hook) {
					\call_user_func($cb_callable, $receiver['callable']);
				}
			} else {
				$rc = $receiver['class'];
				\call_user_func($cb_class, static::getReceiverInstance($rc));
			}
		}

		return $this;
	}
}
