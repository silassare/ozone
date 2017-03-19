<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\FS;

	use OZONE\OZ\Core\OZoneAssert;
	use OZONE\OZ\OZone;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZonePPicUtils {
		/**
		 * @var \OZONE\OZ\FS\OZoneFiles
		 */
		private $file_obj;

		/**
		 * OZonePPicUtils constructor.
		 *
		 * @param string|int $uid the user id
		 */
		public function __construct( $uid ) {
			$this->file_obj = new OZoneFiles( $uid );
		}

		/**
		 * set a profile picture with a given file id and key of an existing file
		 *
		 * @param array      $coordinate the crop zone coordinate
		 * @param string|int $fid        the file id
		 * @param string     $fkey       the file key
		 * @param string     $file_label the file log label
		 *
		 * @return string    the profile picid
		 * @throws \OZONE\OZ\Exceptions\OZoneUnauthorizedActionException
		 */
		public function fromFid( array $coordinate, $fid, $fkey, $file_label = 'OZ_FILE_LABEL_PPIC' ) {
			$result = ( $this->file_obj->setFromFid( $fid, $fkey, true ) ) ? true : false;

			//controle si user a droit d'access sur ce fichier
			OZoneAssert::assertAuthorizeAction( $result );

			//on cre une photo de profile
			$pic_done = $this->file_obj->makeProfilePic( $coordinate );

			//on verifie s'il y a eu erreur lors de la creation de la photo de profile
			OZoneAssert::assertAuthorizeAction( $pic_done, $this->file_obj->getMessage() );

			//on enregistre le fichiers dans la bd
			$finfos = $this->file_obj->logFile( $file_label );

			return $this->asPicid( $finfos );
		}

		/**
		 * set a profile picture from uploaded file
		 *
		 * @param array  $coordinate the crop zone coordinate
		 * @param array  $file       the uploaded file
		 * @param string $file_label the file log label
		 *
		 * @return string    the profile picid
		 * @throws \OZONE\OZ\Exceptions\OZoneUnauthorizedActionException
		 */
		public function fromUploadedFile( array $coordinate, $file, $file_label = 'OZ_FILE_LABEL_PPIC' ) {
			$upload_success = ( $this->file_obj->setFileFromUpload( $file ) ) ? true : false;

			//on verifie s'il y a eu erreur lors du telechargement
			OZoneAssert::assertAuthorizeAction( $upload_success, $this->file_obj->getMessage() );

			//on cre une photo de profile
			$pic_done = $this->file_obj->makeProfilePic( $coordinate );

			//on verifie s'il y a eu erreur lors de la creation de la photo de profile
			OZoneAssert::assertAuthorizeAction( $pic_done, $this->file_obj->getMessage() );

			//on enregistre le fichiers dans la bd
			$finfos = $this->file_obj->logFile( $file_label );

			return $this->asPicid( $finfos );
		}

		/**
		 * reset the profile pic to default
		 *
		 * @return string the profile picid
		 */
		public function toDefault() {
			return '0_0';
		}

		/**
		 * get profile picid from a given file info
		 *
		 * @param array $info the ozone file info
		 *
		 * @return string the profile picid
		 */
		private function asPicid( array $info ) {

			if ( !empty( $info[ 'fid' ] ) AND !empty( $info[ 'fkey' ] ) ) {
				return $info[ 'fid' ] . '_' . $info[ 'fkey' ];
			}

			return $this->toDefault();
		}
	}