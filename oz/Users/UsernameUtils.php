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

namespace OZONE\Core\Users;

use OZONE\Core\Db\OZUsername;

/**
 * Class UsernameUtils.
 */
final class UsernameUtils
{
	/**
	 * Check if a username exists.
	 */
	public static function exists(
		string $name,
	): bool {
		return null !== self::get($name);
	}

	/**
	 * Get a registered username entry.
	 */
	public static function get(
		string $name,
	): ?OZUsername {
		return OZUsername::qb()
			->whereNameIs($name)
			->find(1)->fetchClass();
	}
}
