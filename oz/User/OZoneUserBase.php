<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\User;

	use OZONE\OZ\Core\OZoneDb;
	use OZONE\OZ\Utils\OZoneStr;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	/**
	 * Class OZoneUserBase
	 * @package OZONE\OZ\User
	 */
	abstract class OZoneUserBase {
		/**
		 * the user id
		 *
		 * @var int|string
		 */
		private $uid;

		/**
		 * OZoneUserBase constructor.
		 *
		 * @param string|int $uid the user id
		 */
		protected function __construct( $uid ) {
			$this->uid = $uid;
		}

		/**
		 * get the user id
		 *
		 * @return int|string
		 */
		public function getUid() {
			return $this->uid;
		}

		/**
		 * create a new user with a given user data
		 *
		 * @param array $user_data the user information
		 *
		 * @return string|int    the created user id
		 * @throws \Exception    when user data is not complete
		 */
		public function createNewUser( array $user_data ) {
			$user_data[ 'id' ] = null;
			$user_data[ 'regdate' ] = time();
			$user_data[ 'valid' ] = 1;

			if ( empty( $user_data[ 'phone' ] ) AND empty( $user_data[ 'email' ] ) ) {
				throw new \Exception( "we require email or phone number to create new user." );
			}

			$sql = "INSERT INTO oz_users ( user_id, user_phone, user_email, user_pass, user_name, user_sex, user_bdate,  user_regdate, user_picid, user_cc2, user_valid )
					VALUES( :id, :phone, :email, :pass, :name, :sex, :bdate, :regdate, :picid, :cc2, :valid )";

			$uid = OZoneDb::getInstance()->insert( $sql, $user_data );

			return $uid;
		}

		/**
		 * update user data
		 *
		 * @param string $field the field to update
		 * @param mixed  $value the field value to set
		 *
		 * @return int
		 */
		public function updateUserData( $field, $value ) {
			$uid = $this->uid;

			$sql = "UPDATE oz_users SET " . $field . " =:val WHERE user_id=:me AND user_valid=:v";

			return OZoneDb::getInstance()->update( $sql, array(
				'val' => $value,
				'me'  => $uid,
				'v'   => 1
			) );
		}

		/**
		 * get users data from database
		 *
		 * @param array $list       the users id list
		 * @param bool  $valid_only should we get only valid users data
		 *
		 * @return array the users data: map user id to user data
		 */
		public function getUserData( array $list, $valid_only = true ) {
			$values = OZoneStr::arrayToList( $list );
			$sql = "SELECT * FROM oz_users WHERE user_id IN" . $values;

			if ( !!$valid_only ) {
				$sql .= " AND user_valid = 1";
			}

			$req = OZoneDb::getInstance()->select( $sql );
			$ans = array();

			while ( $data = $req->fetch() ) {
				$ans[ $data[ 'user_id' ] ] = $this->userDataFilter( $data );
			}

			$req->closeCursor();

			return $ans;
		}

		/**
		 * help you to handle user data for filtering or customization purpose
		 *
		 * @param array $user_data the user data to be filtered
		 *
		 * @return array    the filtered user data
		 */
		abstract public function userDataFilter( array $user_data );
	}