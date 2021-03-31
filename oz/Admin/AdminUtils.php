<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Admin;

use Exception;
use OZONE\OZ\Db\OZAdministratorsQuery;
use OZONE\OZ\Exceptions\InternalErrorException;

final class AdminUtils
{
	private static $caches = [];

	/**
	 * Checks if a given user id belong to an admin.
	 *
	 * @param mixed $uid       The user id
	 * @param bool  $use_cache
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 *
	 * @return bool
	 */
	public static function isAdmin($uid, $use_cache = true)
	{
		if (!$use_cache || !isset(self::$caches[$uid])) {
			try {
				$admins = new OZAdministratorsQuery();

				$count = $admins->filterByUserId($uid)
								->filterByValid(1)
								->find(1)
								->count();

				self::$caches[$uid] = ($count === 1);
			} catch (Exception $e) {
				throw new InternalErrorException(null, null, $e);
			}
		}

		return self::$caches[$uid];
	}
}
