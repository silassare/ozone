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

	use OZONE\OZ\Core\OZoneDb;
	use OZONE\OZ\Core\OZoneSettings;
	use OZONE\OZ\Utils\OZoneOmlTextHelper;
	use OZONE\OZ\Utils\OZoneStr;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneFilesUtils {

		/**
		 * the default mime type for any file
		 *
		 * @var string
		 */
		const DEFAULT_FILE_MIME_TYPE = 'application/octet-stream';

		/**
		 * the default extension for any file
		 *
		 * @var string
		 */
		const DEFAULT_FILE_EXTENSION = 'none';

		/**
		 * the default category for any file
		 *
		 * @var string
		 */
		const DEFAULT_FILE_CATEGORY = 'unknown';

		/**
		 * guess mime type that match to a given file extension
		 *
		 * when none found, default to \OZONE\OZ\FS\OZoneFilesUtils::DEFAULT_FILE_MIME_TYPE
		 *
		 * @param string $ext the file extension
		 *
		 * @return string the mime type
		 * @throws \OZONE\OZ\Exceptions\OZoneInternalError when we could not load 'oz.map.ext.to.mime' setting
		 */
		public static function extensionToMimeType( $ext ) {
			$map = OZoneSettings::get( 'oz.map.ext.to.mime' );
			$ext = strtolower( $ext );

			if ( isset( $map[ $ext ] ) ) {
				return $map[ $ext ];
			}

			return OZoneFilesUtils::DEFAULT_FILE_MIME_TYPE;
		}

		/**
		 * guess file extension that match to a given file mime type
		 *
		 * when none found, default to \OZONE\OZ\FS\OZoneFilesUtils::DEFAULT_FILE_EXTENSION
		 *
		 * @param string $type the file mime type
		 *
		 * @return string    the file extension that match to this file mime type
		 * @throws \OZONE\OZ\Exceptions\OZoneInternalError  when we could not load 'oz.map.mime.to.ext' setting
		 */
		public static function mimeTypeToExtension( $type ) {
			$map = OZoneSettings::get( 'oz.map.mime.to.ext' );
			$type = strtolower( $type );

			if ( isset( $map[ $type ] ) ) {
				return $map[ $type ];
			}

			return OZoneFilesUtils::DEFAULT_FILE_EXTENSION;
		}

		/**
		 * guess the file category with a given file mime type (video/audio/image ...)
		 *
		 * when none found, default to \OZONE\OZ\FS\OZoneFilesUtils::DEFAULT_FILE_CATEGORY
		 *
		 * @param string $mime the file mime type
		 *
		 * @return string
		 */
		public static function mimeToCategory( $mime ) {
			if ( !empty( $mime ) ) {
				return strtolower( substr( $mime, 0, strrpos( $mime, '/' ) ) );
			}

			return OZoneFilesUtils::DEFAULT_FILE_CATEGORY;
		}

		/**
		 * check if a given string sequence is like a file key
		 *
		 * @param string $str the file key
		 *
		 * @return bool
		 */
		public static function isFileKeyLike( $str ) {
			$file_key_reg = "#^[a-zA-Z0-9]{32}$#";

			return is_string( $str ) AND preg_match( $file_key_reg, $str );
		}

		/**
		 * format file size with a given file size in byte
		 *
		 * @param double $size the file size in byte
		 *
		 * @return string    the formatted file size
		 */
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

		/**
		 * get file info from database with a given file id and file key
		 *
		 * @param string|int $fid  the file id
		 * @param string     $fkey the file key
		 *
		 * @return array|bool    the file info when successful, false when none found
		 */
		public static function getFileFromFid( $fid, $fkey ) {

			$fkey = OZoneStr::clean( $fkey );

			if ( empty( $fid ) OR !OZoneFilesUtils::isFileKeyLike( $fkey ) ) {
				return false;
			}

			$sql = "
					SELECT * FROM oz_files
					WHERE
						file_id =:fid AND file_key =:fkey
					LIMIT 0,1";

			$req = OZoneDb::getInstance()->select( $sql, array(
				'fid'  => $fid,
				'fkey' => $fkey,
			) );

			$count = $req->rowCount();

			if ( $count === 1 ) {
				$data = $req->fetch();
				$info = array(
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

				$info[ 'ftext' ] = OZoneOmlTextHelper::formatText( 'file', $info );

				$req->closeCursor();

				return $info;
			}

			return false;
		}

		/**
		 * log multiple uploaded files into the database
		 *
		 * @param string|int $uid   the user id
		 * @param array      $files the files list
		 * @param string     $label the files log label
		 *
		 * @return array|string when successful an array of file info, error message when fails
		 */
		public static function logMultipleFiles( $uid, array $files, $label ) {
			$done_list = array();
			$tmp_file_list = array();

			//cas d'upload multiple
			//transformation d'un tableau a trois dimenssions en un tableau a deux dimenssions
			foreach ( $files as $key => $value ) {
				foreach ( $value as $pos => $val ) {
					if ( !isset( $tmp_file_list[ $pos ] ) ) {
						$tmp_file_list[ $pos ] = array();
					}
					$tmp_file_list[ $pos ][ $key ] = $val;
				}
			}

			$error_msg = '';
			$safe = true;
			$file_obj_list = array();

			foreach ( $tmp_file_list as $i => $file ) {
				$file_obj = new OZoneFiles( $uid );

				if ( $file_obj->setFileFromUpload( $file ) ) {
					$file_obj_list[ $i ] = $file_obj;
				} else {
					$error_msg = $file_obj->getMessage();
					$safe = false;
					break;
				}
			}

			if ( $safe ) {

				foreach ( $file_obj_list as $j => $obj ) {
					$done_list[ $j ] = $obj->logFile( $label );
				}

				return $done_list;

			} else {
				return $error_msg;
			}
		}
	}