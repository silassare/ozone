<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZonePPicUtils {
		private $file_obj;

		public function __construct( $uid ) {
			$this->file_obj = OZone::obj( 'OZoneFiles', $uid );
		}

		public function fromFid( array $coords, $fid, $fkey, $file_label = 'OZ_FILE_LABEL_PPIC' ) {
			$result = ( $this->file_obj->setFromFid( $fid, $fkey, true ) ) ? true : false;

			//controle si user a droit d'access sur ce fichier
			OZoneAssert::assertAuthorizeAction( $result );

			//on cre une photo de profile
			$pic_done = $this->file_obj->makeProfilePic( $coords );

			//on verifie s'il y a eu erreur lors de la creation de la photo de profile
			OZoneAssert::assertAuthorizeAction( $pic_done, $this->file_obj->getMsg() );

			//on enregistre le fichiers dans la bd
			$finfos = $this->file_obj->logFile( array(
				'label' => $file_label
			) );

			return $this->asPicid( $finfos );
		}

		public function fromFile( array $coords, $files, $file_label = 'OZ_FILE_LABEL_PPIC' ) {
			$upload_success = ( $this->file_obj->setFileFromUpload( $files[ 'photo' ] ) ) ? true : false;

			//on verifie s'il y a eu erreur lors du telechargement
			OZoneAssert::assertAuthorizeAction( $upload_success, $this->file_obj->getMsg() );

			//on cre une photo de profile
			$pic_done = $this->file_obj->makeProfilePic( $coords );

			//on verifie s'il y a eu erreur lors de la creation de la photo de profile
			OZoneAssert::assertAuthorizeAction( $pic_done, $this->file_obj->getMsg() );

			//on enregistre le fichiers dans la bd
			$finfos = $this->file_obj->logFile( array(
				'label' => $file_label
			) );

			return $this->asPicid( $finfos );
		}

		public function toDefault() {
			return '0_0';
		}

		private function asPicid( array $finfos ) {

			if ( !empty( $finfos[ 'fid' ] ) AND !empty( $finfos[ 'fkey' ] ) ) {
				return $finfos[ 'fid' ] . '_' . $finfos[ 'fkey' ];
			}

			return $this->toDefault();
		}
	}