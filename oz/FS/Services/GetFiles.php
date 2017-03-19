<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\FS\Services;

	use OZONE\OZ\Core\OZoneAssert;
	use OZONE\OZ\Core\OZoneService;
	use OZONE\OZ\Core\OZoneSessions;
	use OZONE\OZ\Core\OZoneSettings;
	use OZONE\OZ\Core\OZoneUri;
	use OZONE\OZ\Exceptions\OZoneForbiddenException;
	use OZONE\OZ\Exceptions\OZoneNotFoundException;
	use OZONE\OZ\FS\OZoneFiles;
	use OZONE\OZ\FS\OZoneFilesUtils;
	use OZONE\OZ\User\OZoneUserUtils;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	class GetFiles extends OZoneService {

		/**
		 * GetFiles constructor.
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * {@inheritdoc}
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneForbiddenException
		 * @throws \OZONE\OZ\Exceptions\OZoneInternalError
		 * @throws \OZONE\OZ\Exceptions\OZoneInvalidFormException
		 * @throws \OZONE\OZ\Exceptions\OZoneNotFoundException
		 */
		public function execute( $request = array() ) {
			$src = null;
			$result = null;
			$params = array( 'fid', 'fkey', 't' );
			$file_uri_reg = OZoneSettings::get( 'oz.files', 'OZ_FILE_URI_EXTRA_REG' );

			$extra_ok = OZoneUri::parseUriExtra( $file_uri_reg, $params, $request );

			if ( !$extra_ok )
				throw new OZoneNotFoundException();

			OZoneAssert::assertForm( $request, $params, new OZoneNotFoundException() );

			$picid = $request[ 'fid' ] . '_' . $request[ 'fkey' ];

			if ( !OZoneUserUtils::userVerified() AND !$this->isLastUserPic( $picid ) ) {
				throw new OZoneForbiddenException();
			}

			$this->labelGetFile( $request );
		}

		/**
		 * process the 'get file' request
		 *
		 * @param $request
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneInternalError
		 * @throws \OZONE\OZ\Exceptions\OZoneNotFoundException
		 */
		private function labelGetFile( $request ) {
			$fid = $request[ 'fid' ];
			$fkey = $request[ 'fkey' ];
			$thumb = intval( $request[ 't' ] );
			$src = null;

			$result = OZoneFilesUtils::getFileFromFid( $fid, $fkey );

			if ( !$result )
				throw new OZoneNotFoundException();

			if ( $thumb > 0 ) {
				//si l'application veut un thumbnails et qu'on ne l'a pas
				//alors on notifie cela; l'application cliente devrais pouvoir prendre en charge cette situation
				//mais quand il s'agira de la version wap/mobilebrowser c'est a dire sans javascript il faudra retourner l'image par defaut pour le type de ce fichier
				if ( empty( $result[ 'fthumb' ] ) )
					throw new OZoneNotFoundException();

				$src = OZoneFiles::toAbsolute( $result[ 'fthumb' ] );

			} else {
				$src = OZoneFiles::toAbsolute( $result[ 'fpath' ] );
			}

			//on verifie s'il y a un probleme sur le lien tirer de la base de donnee
			//TODO disfonctionnement il faudrat peut-etre creer un log afin de resoudre ce probleme de lien
			if ( empty( $src ) || !file_exists( $src ) || !is_file( $src ) || !is_readable( $src ) )
				throw new OZoneNotFoundException();

			$path_parts = pathinfo( $src );
			$ext = strtolower( $path_parts[ 'extension' ] );

			$fmime = OZoneFilesUtils::extensionToMimeType( $ext );

			$fname = OZoneSettings::get( 'oz.files', 'OZ_FILE_DOWNLOAD_NAME' );
			$fname = str_replace( array(
				'{oz_fid}',
				'{oz_thumb}',
				'{oz_ext}'
			), array(
				$fid,
				$thumb,
				$ext
			), $fname );

			( new FilesServer() )->execute( array(
				'src'   => $src,
				'thumb' => $thumb,
				'fname' => $fname,
				'fmime' => $fmime
			) );
		}

		/**
		 * check if a given picid is the current user profile picid
		 *
		 * @param string $picid the file picture file id
		 *
		 * @return bool
		 * @throws \Exception
		 */
		private function isLastUserPic( $picid ) {

			$uid = OZoneSessions::get( 'ozone_user:user_id' );

			if ( !empty( $uid ) ) {

				$info = OZoneUserUtils::getUserObject( $uid )->getUserData( array( $uid ) );

				if ( isset( $info[ $uid ] ) AND $info[ $uid ][ 'picid' ] === $picid ) {
					return true;
				}
			}

			return false;
		}
	}