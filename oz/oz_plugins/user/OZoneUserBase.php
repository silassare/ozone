<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	abstract class OZoneUserBase {
		private $uid;

		protected function __construct( $uid ) {

			$this->uid = $uid;
		}

		public function getUid() {

			return $this->uid;
		}

		public function createNewUser( array $uinfos ) {
			$uinfos[ 'id' ] = null;
			$uinfos[ 'regdate' ] = time();
			$uinfos[ 'valid' ] = 1;

			if ( empty( $uinfos[ 'phone' ] ) AND empty( $uinfos[ 'email' ] ) ) {
				throw new Exception( "we require email or phone number to create new user." );
			}

			//on inscrit user
			$sql = "INSERT INTO oz_users ( user_id, user_phone, user_email, user_pass, user_name, user_sexe, user_bdate,  user_regdate, user_picid, user_cc2, user_valid )
					VALUES( :id, :phone, :email, :pass, :name, :sexe, :bdate, :regdate, :picid, :cc2, :valid )";

			$uid = OZone::obj( 'OZoneDb' )->insert( $sql, $uinfos );

			return $uid;
		}

		//update user infos
		public function updateUserInfos( $field, $value ) {
			$uid = $this->uid;

			$sql = "UPDATE oz_users SET " . $field . " =:val WHERE user_id=:me AND user_valid=:v";

			return OZone::obj( 'OZoneDb' )->update( $sql, array(
				'val' => $value,
				'me'  => $uid,
				'v'   => 1
			) );
		}

		//pour la recupereration des infos de user à partir de la base de donnees
		public function getUserInfos( array $list, $valid_only = true ) {
			$uid = $this->uid;
			$values = OZoneStr::arrayToList( $list );
			$sql = "SELECT * FROM oz_users WHERE user_id IN" . $values;

			if ( !!$valid_only ) {
				$sql .= " AND user_valid = 1";
			}

			$req = OZone::obj( 'OZoneDb' )->select( $sql );
			$ans = array();

			while ( $data = $req->fetch() ) {
				$ans[ $data[ 'user_id' ] ] = $this->userInfosFilter( $data );
			}

			$req->closeCursor();

			return $ans;
		}

		//help you to handle user infos data for filtering or customization purpose
		abstract public function userInfosFilter( array $uinfos );
	}