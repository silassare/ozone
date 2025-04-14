<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\Core\Access;

use RuntimeException;

/**
 * Class AtomicActionsRegistry.
 */
class AtomicActionsRegistry
{
	/**
	 * @var array<string, AtomicAction>
	 */
	private static array $registry = [];

	/**
	 * Registers an action with its corresponding access right.
	 *
	 * @param AtomicAction $accessRight
	 */
	public static function register(AtomicAction $accessRight): void
	{
		$action = $accessRight->getAction();
		if (isset(self::$registry[$action])) {
			throw new RuntimeException("Action '{$action}' already registered.");
		}

		self::$registry[$action] = $accessRight;
	}

	/**
	 * Gets the access right for a specific action.
	 *
	 * @param string $action
	 *
	 * @return null|AtomicAction
	 */
	public static function get(string $action): ?AtomicAction
	{
		return self::$registry[$action] ?? null;
	}

	/**
	 * Checks if an action is registered.
	 *
	 * @param string $action
	 *
	 * @return bool
	 */
	public static function isRegistered(string $action): bool
	{
		return isset(self::$registry[$action]);
	}

	/**
	 * Gets all registered actions.
	 *
	 * @return array<string, AtomicAction>
	 */
	public static function getAll(): array
	{
		return self::$registry;
	}
}
