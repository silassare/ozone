<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Admin;

	use OZONE\OZ\Db\OZAdministratorsQuery;
	use OZONE\OZ\Exceptions\InternalErrorException;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class AdminUtils
	{
		/**
		 * Checks if a given user id belong to an admin.
		 *
		 * @param mixed $uid The user id
		 *
		 * @return bool
		 * @throws \OZONE\OZ\Exceptions\BaseException
		 */
		public static function isAdmin($uid)
		{
			try {
				$admins = new OZAdministratorsQuery();

				$count = $admins->filterByUserId($uid)
								->filterByValid(1)
								->find(1)
								->count();
			} catch (\Exception $e) {
				throw new InternalErrorException(null, null, $e);
			}

			return ($count === 1 ? true : false);
		}
	}