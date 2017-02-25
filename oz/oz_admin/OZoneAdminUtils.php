<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneAdminUtils {

		public static function isAdmin( $uid ) {
			$sql = "
				SELECT * FROM oz_users , oz_administrators 
				WHERE oz_administrators.user_id =:uid 
					AND oz_administrators.admin_valid = 1
					AND oz_administrators.user_id = oz_users.user_id
					AND oz_users.user_valid = 1
				LIMIT 0,1";

			$req = OZone::obj( 'OZoneDb' )->select( $sql, array(
				'uid' => $uid
			) );

			return $req->rowCount() > 0;
		}
	}