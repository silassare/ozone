<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Hooks\Interfaces;

\defined('OZ_SELF_SECURITY_CHECK') || die;

interface HookProviderInterface
{
	const RUN_FIRST   = 1;

	const RUN_DEFAULT = 2;

	const RUN_LAST    = 3;

	/**
	 * Gets a hook provider instance.
	 *
	 * @return $this
	 */
	public static function getInstance();

	/**
	 * Called to get receiver instance.
	 *
	 * @param string $hook_receiver_class
	 *
	 * @return HookReceiverInterface
	 */
	public static function getReceiverInstance($hook_receiver_class);

	/**
	 * Checks if the hook receiver class is compatible to the hook provider.
	 *
	 * @param mixed $hook_receiver_class
	 *
	 * @return bool
	 */
	public static function isCompatibleHookReceiverClass($hook_receiver_class);

	/**
	 * Register a hook receiver class.
	 *
	 * @param string $hook_receiver_class
	 * @param int    $priority
	 *
	 * @return static
	 */
	public static function registerHookReceiverClass($hook_receiver_class, $priority);
}
