<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	class OZoneServiceGetFiles extends OZoneService {
		public function __construct() {
			parent::__construct();
		}

		public function execute( $request = array() ) {
			$src = null;
			$result = null;
			$params = array( 'fid', 'fkey', 't' );
			$file_uri_reg = OZoneSettings::get( 'oz.files', 'OZ_FILE_URI_EXTRA_REG' );

			$extra_ok = OZoneUri::parseServiceUriExtra( $file_uri_reg, $params, $request );

			OZoneAssert::assertAuthorizeAction( $extra_ok, new OZoneErrorNotFound() );

			//on verifie si le formulaire est valide ou non
			OZoneAssert::assertForm( $request, $params, new OZoneErrorNotFound() );

			$picid = $request[ 'fid' ] . '_' . $request[ 'fkey' ];

			if ( !OZoneUserUtils::userVerified() AND !$this->isLastUserPic( $picid ) ) {
				throw new OZoneErrorForbidden();
			}

			$this->labelGetFile( $request );
		}

		private function labelGetFile( $request ) {
			$fid = $request[ 'fid' ];
			$fkey = $request[ 'fkey' ];
			$thumb = intval( $request[ 't' ] );
			$src = null;

			$result = OZoneFilesUtils::getFileFromFid( $fid, $fkey );

			if ( !$result )
				throw new OZoneErrorNotFound();

			if ( $thumb > 0 ) {
				//si l'application veut un thumbnails et qu'on ne l'a pas
				//alors on notifie cela; l'application cliente devrais pouvoir prendre en charge cette situation
				//mais quand il s'agira de la version wap/mobilebrowser c'est a dire sans javascript il faudra retourner l'image par defaut pour le type de ce fichier
				if ( empty( $result[ 'fthumb' ] ) )
					throw new OZoneErrorNotFound();

				$src = OZoneFiles::toAbsolute( $result[ 'fthumb' ] );

			} else {
				$src = OZoneFiles::toAbsolute( $result[ 'fpath' ] );
			}

			//on verifie s'il y a un probleme sur le lien tirer de la base de donnee
			//SILO::TODO disfonctionnement il faudrat peut-etre creer un log afin de resoudre ce probleme de lien erronn�
			if ( empty( $src ) || !file_exists( $src ) || !is_file( $src ) || !is_readable( $src ) )
				throw new OZoneErrorNotFound();

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

			OZone::obj( 'OZoneServiceFilesServer' )->execute( array(
				'src'   => $src,
				'thumb' => $thumb,
				'fname' => $fname,
				'fmime' => $fmime
			) );
		}

		//verifie si le fichier demander est l'image de profile de l'user qui vient de se deconnecter
		//utile pour la page de connexion de l'application
		private function isLastUserPic( $picid ) {

			$uid = OZoneSessions::get( 'ozone_user:user_id' );

			if ( !empty( $uid ) ) {

				$infos = OZoneUserUtils::getUserObject( $uid )->getUserInfos( array( $uid ) );

				if ( isset( $infos[ $uid ] ) AND $infos[ $uid ][ 'picid' ] === $picid ) {
					return true;
				}
			}

			return false;
		}
	}