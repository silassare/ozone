<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Admin;

	use OZONE\OZ\Core\OZoneDb;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class AdminUtils
	{

		/**
		 * Check if a given user id belong to an admin.
		 *
		 * @param mixed $uid The user id
		 *
		 * @return bool
		 */
		public static function isAdmin($uid)
		{
			$sql = "
				SELECT * FROM oz_users , oz_administrators 
				WHERE oz_administrators.user_id =:uid 
					AND oz_administrators.admin_valid = 1
					AND oz_administrators.user_id = oz_users.user_id
					AND oz_users.user_valid = 1
				LIMIT 0,1";

			$req = OZoneDb::getInstance()
						  ->select($sql, ['uid' => $uid]);

			return $req->rowCount() > 0;
		}
	}