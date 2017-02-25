<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneFiles {
		private $uid;
		private $dest_dir;
		private $thumb_dir;
		private $msg = null;
		private $infos = array(
			'fid'    => null,
			'uid'    => null,
			'fkey'   => null,
			'fclone' => null,
			'ftype'  => null,
			'fname'  => null,
			'flabel' => null,
			'fpath'  => null,
			'fthumb' => null,
			'ftime'  => null
		);

		function __construct( $uid ) {
			$this->infos[ 'uid' ] = $this->uid = $uid;
			$this->dest_dir = OZ_APP_USERS_FILES_DIR . $uid;
			$this->thumb_dir = OZ_APP_USERS_FILES_DIR . $uid . DS . 'thumb';

			//creons  les dossiers propres a cet user si ce n'est pas deja le cas
			$this->createDir( $this->dest_dir );
			$this->createDir( $this->thumb_dir );
		}

		private function cloneFile( $infos ) {
			$this->infos = $infos;

			//SILO clone de premier niveau uniquement > pour preserver la trace
			if ( !$this->isClone() ) {
				$this->infos[ 'fclone' ] = $this->infos[ 'fid' ];
			}

			//force la creation d'un nouveau fid
			$this->infos[ 'fid' ] = null;
			//ce clone est la propriete de ce user
			$this->infos[ 'uid' ] = $this->uid;
		}

		//prend en charge un fichier existant dans la base de donnees grace a sont fid
		public function setFromFid( $fid, $key, $clone = false ) {
			$infos = OZoneFilesUtils::getFileFromFid( $fid, $key );
			if ( !$infos ) {
				$this->msg = 'OZ_FILE_NOT_FOUND';

				return false;
			}

			if ( $clone ) {
				$this->cloneFile( $infos );
			} else {
				$this->infos = $infos;
			}

			return true;
		}

		//SILO::ALERT please be aware
		private function isUplodedFileAlias( $f ) {
			//un nom et une extension correcte
			if ( !preg_match( "#^[a-z0-9_]+\.ofa$#i", $f[ 'name' ] ) )
				return false;
			//doit avoir un type mime correct
			if ( $f[ 'type' ] !== "text/x-ozone-file-alias" )
				return false;
			//ne doit pas depasser 4ko
			if ( $f[ 'size' ] > 4 * 1024 )
				return false;

			return true;
		}

		//SILO::ALERT please be aware
		private function parseFileAlias( $src ) {
			if ( empty( $src ) || !file_exists( $src ) || !is_file( $src ) || !is_readable( $src ) ) {
				OZoneAssert::assertAuthorizeAction( false, 'OZ_FILE_ALIAS_UNKNOWN' );
			}

			$desc = file_get_contents( $src );
			$data = json_decode( $desc, true );

			if ( !is_array( $data ) OR !array_key_exists( 'fid', $data ) OR !array_key_exists( 'fkey', $data ) ) {
				OZoneAssert::assertAuthorizeAction( false, 'OZ_FILE_ALIAS_PARSE_ERROR' );
			}

			$infos = OZoneFilesUtils::getFileFromFid( $data[ 'fid' ], $data[ 'fkey' ] );

			if ( !$infos ) {
				OZoneAssert::assertAuthorizeAction( false, 'OZ_FILE_ALIAS_NOT_FOUND' );//SILO::TODO MAYBE FORBIDDEN
			}

			move_uploaded_file( $src, $this->dest_dir . DS . $data[ 'fid' ] . ".ofa" );

			return $infos;
		}

		//prend en charge un fichier issu d'un upload
		public function setFileFromUpload( $f ) {
			//on verifie si l'upload s'est bien passee
			if ( ( int ) $f[ 'error' ] != UPLOAD_ERR_OK ) {
				$this->codeToMessage( $f[ 'error' ] );

				return false;
			}

			if ( !is_uploaded_file( $f[ 'tmp_name' ] ) ) {
				$this->msg = 'OZ_FILE_NOT_VALID';

				return false;
			}

			if ( $this->isUplodedFileAlias( $f ) ) {
				$result = $this->parseFileAlias( $f[ 'tmp_name' ] );
				$this->cloneFile( $result );

				return true;
			}

			//on enregistre le fichier
			$fname = $f[ 'name' ];//le nom du fichier sur le mobile/ordinateur de user
			$ftype = $f[ 'type' ];//mime type ex: image/png
			//cas d'un enregistrement audio/video ou d'une photo capturer par user
			if ( $fname === 'blob' ) {
				$fname = $f[ 'name' ] = $this->getOptionalFileName( $ftype );
			}

			$uid = $this->infos[ 'uid' ];
			$gen_infos = $this->genFileInfos( $fname, $ftype );
			$destination = $gen_infos[ 'dest_path' ];
			$thumb_dest = $gen_infos[ 'thumb_path' ];

			$this->safeDelete( $destination );

			//on essaye de deplacer le fichier vers la destination
			$result = move_uploaded_file( $f[ 'tmp_name' ], $destination );

			if ( $result ) {
				$this->infos[ 'fname' ] = $fname;
				$this->infos[ 'fpath' ] = $destination;
				$this->infos[ 'ftype' ] = $ftype;
				$this->infos[ 'fsize' ] = filesize( $destination );

				//on cre un thumbnail si possible
				$this->makeThumb( $ftype, $destination, $thumb_dest );

				$this->msg = 'OZ_FILE_SAVE_SUCCESS';

				return true;
			} else {
				$this->msg = 'OZ_FILE_SAVE_FAIL';

				return false;
			}
		}

		public function genFileInfos( $source_name, $source_type ) {
			$uid = $this->uid;

			$ext = $this->getExtension( $source_name, $source_type );//extension du fichier
			$name = $uid . '_' . time() . '_' . rand( 111111, 999999 );
			$name_full = $name . '.' . $ext;
			$dest = $this->dest_dir . DS . $name_full;
			$thumb = $this->thumb_dir . DS . $name . '.jpg';

			return array(
				'name'       => $name,
				'name_full'  => $name_full,
				'dest_path'  => $dest,
				'thumb_path' => $thumb
			);
		}

		public function makeProfilePic( array $coords ) {
			$img_utils_obj = OZone::obj( 'OZoneImagesUtils', $this->infos[ 'fpath' ] );
			$sizex = $sizey = OZoneSettings::get( 'oz.user', 'OZ_PPIC_MIN_SIZE' );
			$quality = 100;//qualite de l'image jpeg: de 0 a 100
			$safe_coords = null;

			//SILO chaque clone a son propre profile pic thumb
			//parceque un profile pic peut etre different a cause de la zone de crop qui est definie par user
			if ( $this->isClone() ) {
				$gen_infos = $this->genFileInfos( $this->infos[ 'fname' ], $this->infos[ 'ftype' ] );
				$this->infos[ 'fthumb' ] = $gen_infos[ 'thumb_path' ];
			}

			$thumb = $this->infos[ 'fthumb' ];

			if ( !empty( $coords ) AND isset( $coords[ 'x' ] ) AND isset( $coords[ 'y' ] ) AND isset( $coords[ 'w' ] ) AND isset( $coords[ 'h' ] ) ) {
				$safe_coords = array(
					'x' => intval( $coords[ 'x' ] ),
					'y' => intval( $coords[ 'y' ] ),
					'w' => intval( $coords[ 'w' ] ),
					'h' => intval( $coords[ 'h' ] )
				);
			}

			if ( $img_utils_obj->load() ) {
				if ( is_null( $safe_coords ) ) {
					$img_utils_obj->cropAndSave( $thumb, $quality, $sizex, $sizey );
				} else {
					$img_utils_obj->cropAndSave( $thumb, $quality, $sizex, $sizey, $coords, false );
				}
			} else {
				//le fichier n'est pas une image valide
				$this->msg = 'OZ_IMAGE_NOT_VALID';
				//ne pas commettre l'erreur de supprimer un fichier deja enregistré dans la bd
				$this->safeDelete( $this->infos[ 'fpath' ] );
				$this->safeDelete( $thumb );

				return false;
			}

			return true;
		}

		public function isClone() {
			return !empty( $this->infos[ 'fclone' ] );
		}

		public function hasThumb() {
			$thumb = $this->infos[ 'fthumb' ];

			return !empty( $thumb ) AND file_exists( $thumb );
		}

		//permet de creer un apercu d'une image, d'une video et pourquoi pas d'un document
		private function makeThumb( $ftype, $source, $dest ) {
			$quality = 50;
			$maxThumbW = $maxThumbH = OZoneSettings::get( 'oz.user', 'OZ_THUMB_MAX_SIZE' );
			$done = false;
			//on recupere la categorie grace au mime type
			$fcat = OZoneFilesUtils::mimeToCategory( $ftype );

			//SILO only thumb may vary so please no duplication, search for --> #REF1
			if ( $this->hasThumb() ) {
				return true;
			}

			switch ( $fcat ) {
				case 'image':
					$img_utils_obj = OZone::obj( 'OZoneImagesUtils', $source );

					if ( $img_utils_obj->load() ) {
						$advice = $img_utils_obj->adviceBestSize( $maxThumbW, $maxThumbH );

						$img_utils_obj->resizeImage( $advice[ 'w' ], $advice[ 'h' ], $advice[ 'crop' ] )->saveImage( $dest, $quality );

						$done = true;
					}
					break;
				case 'video':
					//on essaye de prendre un appercue si possible
					$vidUtils = OZone::obj( 'OZoneVideosUtils', $source );
					if ( $vidUtils->load() ) {
						$done = $vidUtils->makeVideoThumb( $dest );
						if ( $done ) {
							$img_utils_obj = OZone::obj( 'OZoneImagesUtils', $dest );

							if ( $img_utils_obj->load() ) {
								$advice = $img_utils_obj->adviceBestSize( $maxThumbW, $maxThumbH );

								$img_utils_obj->resizeImage( $advice[ 'w' ], $advice[ 'h' ], $advice[ 'crop' ] )->saveImage( $dest, $quality );
							}
						}
					}
					break;
				default:
					//otherfiles
			}

			if ( $done ) {
				$this->infos[ 'fthumb' ] = $dest;
			}

			return $done;
		}

		public function getExtension( $path, $type ) {

			return strtolower( substr( $path, ( strrpos( $path, '.' ) + 1 ) ) );
		}

		//done un nom au fichier qui n'en ont pas
		//c'est le cas pour les blob
		public function getOptionalFileName( $ftype ) {
			//voice-uid-time.ext
			//ex: voice-1000000000-14545454545.wav
			$ext = 'ext';
			switch ( $ftype ) {
				case "image/png":
				case "image/jpeg":
				case "image/jpg":
				case "audio/wav":
					$ext = strtolower( substr( $ftype, ( strrpos( $ftype, '/' ) + 1 ) ) );
					break;
			}

			$name = $this->uid . "-" . time() . "." . $ext;

			return $name;
		}

		//cette methode enregistre les infos sur le fichier dans la base de donnees et retourne les infos complets sur le fichier
		public function logFile( $data ) {
			//on verifi si ce fichier a deja un fid: c'est a dire est deja enregistré dans la base de donnees
			if ( empty( $this->infos[ 'fid' ] ) ) {

				$this->infos[ 'fname' ] = OZoneStr::clean( $this->infos[ 'fname' ] );//le nom du fichier en local chez user
				$this->infos[ 'flabel' ] = OZoneStr::clean( $data[ 'label' ] );//peut etre une chaine vide
				$this->infos[ 'ftime' ] = time();
				$this->infos[ 'fkey' ] = OZoneKeyGen::genFileKey( $this->infos[ 'fpath' ] );

				$sql = "INSERT INTO oz_files( file_id,user_id,file_key,file_clone,file_type,file_size,file_name,file_label,file_path,file_thumb,file_upload_time )
					VALUES( :fid,:uid,:fkey,:fclone,:ftype,:fsize,:fname,:flabel,:fpath,:fthumb,:ftime )";

				$insert = OZone::obj( 'OZoneDb' )->insert( $sql, array(
					'fid'    => null,
					'uid'    => $this->infos[ 'uid' ],
					'fkey'   => $this->infos[ 'fkey' ],
					'fclone' => $this->infos[ 'fclone' ],
					'ftype'  => $this->infos[ 'ftype' ],
					'fsize'  => $this->infos[ 'fsize' ],
					'fname'  => $this->infos[ 'fname' ],
					'flabel' => $this->infos[ 'flabel' ],
					'fpath'  => self::toRelative( $this->infos[ 'fpath' ] ),
					'fthumb' => self::toRelative( $this->infos[ 'fthumb' ] ),
					'ftime'  => $this->infos[ 'ftime' ]
				) );

				$this->infos[ 'fid' ] = $insert;
			}

			$this->infos[ 'ftext' ] = OZoneOmlTextHelper::formatText( 'file', $this->infos );

			return $this->infos;
		}

		public static function toRelative( $path ) {

			if ( !empty( $path ) ) {
				return str_replace( OZ_APP_USERS_FILES_DIR, '', $path );
			}

			return $path;
		}

		public static function toAbsolute( $path ) {

			if ( !empty( $path ) AND !is_int( strpos( $path, OZ_APP_USERS_FILES_DIR ) ) ) {
				return OZ_APP_USERS_FILES_DIR . $path;
			}

			return $path;
		}

		//fait exactement ce que dit sont nom
		public function createDir( $dir ) {
			if ( !file_exists( $dir ) ) {
				mkdir( $dir, 0777 );
			}
		}

		//supprime le fichier pointer par $path
		//sans toutefois commettre l'erreur de supprimer un fichier deja enregistré dans la bd
		//ou un fichier cloner
		public function safeDelete( $path ) {
			if ( !empty( $this->infos[ 'fid' ] ) AND empty( $this->infos[ 'fclone' ] ) AND file_exists( $path ) ) {
				unlink( $path );
			}
		}

		//choisi le message correspondant a chaque erreur qui peut subvenir lors d'un upload
		public function codeToMessage( $code ) {
			switch ( $code ) {
				case UPLOAD_ERR_INI_SIZE:
					//'The uploaded file exceeds the upload_max_filesize directive in php.ini'
				case UPLOAD_ERR_FORM_SIZE:
					//'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'
					$this->msg = 'OZ_FILE_TOO_BIG';
					break;

				case UPLOAD_ERR_NO_FILE:
					//'No file was uploaded'
					$this->msg = 'OZ_FILE_IS_EMPTY';
					break;

				case UPLOAD_ERR_PARTIAL:
					//'The uploaded file was only partially uploaded'
				case UPLOAD_ERR_NO_TMP_DIR:
					//'Missing a temporary folder'
				case UPLOAD_ERR_CANT_WRITE:
					//'Failed to write file to disk'
				case UPLOAD_ERR_EXTENSION:
					//'File upload stopped by extension'
				default:
					//'Unknown upload error'
					$this->msg = 'OZ_FILE_UPLOAD_FAIL';
			}
		}

		//retourne le message d'erreur le plus recent
		public function getMsg() {
			if ( !empty( $this->msg ) )
				return $this->msg;

			return 'OZ_ERROR_INTERNAL';
		}

		//retourne les infos du fichier
		public function getInfos() {
			return $this->infos;
		}
	}