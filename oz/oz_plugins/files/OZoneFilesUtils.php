<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneFilesUtils {

		public static function extensionToMimeType( $ext ) {
			$mime = "application/octet-stream";//<= par defaut, pourquoi pas "application/force-download" ?
			$mimes_list = OZoneSettings::get( 'oz.mimes.list' );

			/*
			$infos = finfo_open( FILEINFO_MIME_TYPE );
			$mime = finfo_file( $infos,$src );
			finfos_close( $infos );
			*/

			if ( is_array( $mimes_list ) AND isset( $mimes_list[ $ext ] ) ) {
				$mime = $mimes_list[ $ext ][ 0 ];
			}

			return $mime;
		}

		public static function mimeToCategory( $mime ) {
			if ( !empty( $mime ) ) {
				return strtolower( substr( $mime, 0, strrpos( $mime, '/' ) ) );
			}

			return 'unknown';
		}

		public static function isFileKeyLike( $fkey ) {
			$fkey_reg = "#^[a-zA-Z0-9]{32}$#";

			return is_string( $fkey ) AND preg_match( $fkey_reg, $fkey );
		}

		public static function formatSize( $size ) {

			$unites = array( 'byte', 'Kb', 'Mb', 'Gb', 'Tb' );
			$max_i = count( $unites );
			$i = 0;
			$result = 0;
			$decimal_point = '.';// ',' for french
			$sep = ' ';

			while ( $size >= 1 AND $i < $max_i ) {
				$result = $size;
				$size /= 1024;
				$i++;
			}

			if ( !$i )
				$i = 1;

			$parts = explode( '.', $result );

			if ( $parts[ 0 ] != $result ) {
				$result = number_format( $result, 2, $decimal_point, $sep );
			}

			return $result . ' ' . $unites[ $i - 1 ];
		}

		public static function getFileFromFid( $fid, $fkey ) {
			//SILO:
			//	- la clef fkey est aubligatoire
			//	- cas de sendfid dans la messagerie

			//important don't trust user input
			$fkey = OZoneStr::clean( $fkey );

			if ( empty( $fid ) OR !OZoneFilesUtils::isFileKeyLike( $fkey ) ) {
				return false;
			}

			$sql = "SELECT * FROM oz_files
					WHERE
						file_id =:fid AND file_key =:fkey
					LIMIT 0,1";

			$req = OZone::obj( 'OZoneDb' )->select( $sql, array(
				'fid'  => $fid,
				'fkey' => $fkey,
			) );

			$count = $req->rowCount();

			if ( $count === 1 ) {
				$data = $req->fetch();
				$infos = array(
					'uid'    => $data[ 'user_id' ],
					'fid'    => $data[ 'file_id' ],
					'fkey'   => $data[ 'file_key' ],
					'fclone' => $data[ 'file_clone' ],
					'ftype'  => $data[ 'file_type' ],
					'fsize'  => $data[ 'file_size' ],
					'flabel' => $data[ 'file_label' ],
					'fname'  => $data[ 'file_name' ],
					'fpath'  => $data[ 'file_path' ],
					'fthumb' => $data[ 'file_thumb' ],
					'ftime'  => $data[ 'file_upload_time' ]
				);

				$infos[ 'ftext' ] = OZoneOmlTextHelper::formatText( 'file', $infos );

				$req->closeCursor();

				return $infos;
			}

			return false;
		}

		public static function logMultipleFile( $uid, $files, $label ) {
			$loggedFileList = array();
			$tmpFileList = array();
			//cas d'upload multiple
			//transformation d'un tableau a trois dimenssions en un tableau a deux dimenssions
			foreach ( $files as $cle => $valeur ) {
				foreach ( $valeur as $key => $val ) {
					if ( !isset( $tmpFileList[ $key ] ) ) {
						$tmpFileList[ $key ] = array();
					}
					$tmpFileList[ $key ][ $cle ] = $val;
				}
			}

			$errormsg = '';
			$safe = true;

			foreach ( $tmpFileList as $key => $f ) {
				$file_obj = OZone::obj( 'OZoneFiles', $uid );

				if ( $file_obj->setFileFromUpload( $f ) ) {
					$loggedFileList[] = $file_obj->logFile( array(
						'label' => $label
					) );
				} else {
					//ALERT::TODO
					//SILO:: IL FAUT TROUVER UN MOYEN DE SUPPRIMER TOUS LES FICHIERS ENREGISTRER
					//dans la base de donner aussi
					//un probleme avec le ou les fichiers

					$errormsg = $file_obj->getMsg();
					$safe = false;

					//on sort de la boucle
					break;
				}
			}

			if ( $safe ) {
				return array( true, $loggedFileList );
			} else {
				return array( false, $errormsg );
			}
		}
	}