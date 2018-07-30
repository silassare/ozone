<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Admin;

	use OZONE\OZ\Db\OZAdministratorsQuery;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class AdminUtils
	{
		/**
		 * Checks if a given user id belong to an admin.
		 *
		 * @param mixed $uid The user id
		 *
		 * @return bool
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Exception
		 */
		public static function isAdmin($uid)
		{
			$admins = new OZAdministratorsQuery();
			$count = $admins->filterByUserId($uid)
						   ->filterByValid(1)
						   ->find(1)
						   ->count();

			return ($count === 1 ? true : false);
		}
	}