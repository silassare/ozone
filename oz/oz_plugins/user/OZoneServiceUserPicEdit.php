<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	class OZoneServiceUserPicEdit extends OZoneService {
		public function __construct() {
			parent::__construct();
		}

		public function execute( $request = array() ) {
			OZoneAssert::assertUserVerified();
			OZoneAssert::assertForm( $request, array( 'forid' ) );

			$label = 'file';

			if ( isset( $request[ 'label' ] ) ) {
				$label = $request[ 'label' ];
			}

			OZoneAssert::assertAuthorizeAction( in_array( $label, array( 'file', 'fid', 'def' ) ) );

			$forid = $request[ 'forid' ];

			$uid = OZoneSessions::get( 'ozone_user:user_id' );
			$file_label = "OZ_FILE_LABEL_USER_PPIC";
			$msg = 'OZ_PROFILE_PIC_CHANGED';

			OZoneAssert::assertAuthorizeAction( $uid === $forid );

			$user_obj = OZoneUserUtils::getUserObject( $uid );

			$ppic_obj = OZone::obj( 'OZonePPicUtils', $uid );

			if ( $label === 'fid' ) {
				OZoneAssert::assertForm( $request, array( 'fid', 'fkey' ) );
				$picid = $ppic_obj->fromFid( $request, $request[ 'fid' ], $request[ 'fkey' ], $file_label );
			} else if ( $label === 'file' ) {
				OZoneAssert::assertForm( $_FILES, array( 'photo' ) );
				$picid = $ppic_obj->fromFile( $request, $_FILES, $file_label );
			} else {//def
				$picid = $ppic_obj->toDefault();
				$msg = 'OZ_PROFILE_PIC_SET_TO_DEFAULT';
			}

			$user_obj->updateUserInfos( 'user_picid', $picid );

			self::$resp->setDone( $msg )->setData( array( 'picid' => $picid ) );
		}
	}