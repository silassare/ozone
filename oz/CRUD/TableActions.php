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

namespace OZONE\Core\CRUD;

use Gobl\CRUD\Enums\ActionType;

/**
 * Class TableActions.
 *
 * The actions a table is asked for, and the name each one has as an access right
 * (`{morph type}.{name}`): read off gobl's own `ActionType`, so the rights registered, the rules a
 * handler declares and the checks it runs are one list. A column action (a primary key, a private
 * or a sensitive column written) is no table action, and has no right.
 */
final class TableActions
{
	/**
	 * The table actions, in the order a matrix lists them.
	 */
	public const array CASES = [
		ActionType::CREATE,
		ActionType::CREATE_BULK,
		ActionType::READ,
		ActionType::READ_ALL,
		ActionType::UPDATE,
		ActionType::UPDATE_ALL,
		ActionType::DELETE,
		ActionType::DELETE_ALL,
	];

	/**
	 * The name of an action as a right and as a rule: `create`, `create_bulk`, `read_all`...
	 */
	public static function nameOf(ActionType $action): string
	{
		return \strtolower($action->value);
	}

	/**
	 * Every table action's name.
	 *
	 * @return list<string>
	 */
	public static function names(): array
	{
		return \array_map(self::nameOf(...), self::CASES);
	}

	/**
	 * Whether a name is a table action's.
	 */
	public static function isName(string $name): bool
	{
		return \in_array($name, self::names(), true);
	}
}
