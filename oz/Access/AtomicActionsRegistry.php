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

namespace OZONE\Core\Access;

use OZONE\Core\Access\Interfaces\AtomicActionInterface;
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
	 * An action registered again with the same definition is kept as it is: the actions of a REST
	 * service are named after its table, so a second service over the same table registers the same
	 * ones. A different definition under a registered name is a conflict, and throws.
	 */
	public static function register(AtomicAction $accessRight): void
	{
		$action     = $accessRight->getAction();
		$registered = self::$registry[$action] ?? null;

		if (null !== $registered) {
			if ($registered->toArray() === $accessRight->toArray()) {
				return;
			}

			throw new RuntimeException("Action '{$action}' already registered with another definition.");
		}

		self::$registry[$action] = $accessRight;
	}

	/**
	 * Gets the access right for a specific action.
	 */
	public static function get(string $action): ?AtomicAction
	{
		return self::$registry[$action] ?? null;
	}

	/**
	 * Checks if an action is registered.
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

	/**
	 * Read atomic actions for a specific user access rights.
	 *
	 * @return array<string, array{action: AtomicActionInterface, allowed: bool}>
	 */
	public static function read(AccessRights $user_access_rights): array
	{
		$all    = self::getAll();
		$result = [];

		foreach ($all as $key => $action) {
			$result[$key] = [
				'action'  => $action,
				'allowed' => $user_access_rights->can($key),
			];
		}

		return $result;
	}
}
