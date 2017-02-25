<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	class OZoneUser extends OZoneUserBase {
		function __construct( $uid ) {
			parent::__construct( $uid );
		}

		//help you to handle user infos data for filtering or customization purpose
		public function userInfosFilter( array $uinfos ) {

			//on evite d'envoyer des infos sensibles comme le mot de passe
			return OZoneDb::mapDbFieldsToExtern( $uinfos, array(
				'user_id',
				'user_name',
				'user_phone',
				'user_email',
				'user_cc2',
				'user_sexe',
				'user_bdate',
				'user_picid'
			) );
		}
	}
