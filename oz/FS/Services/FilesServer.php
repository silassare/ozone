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
	use OZONE\OZ\Core\OZoneSettings;
	use OZONE\OZ\Exceptions\OZoneInternalError;
	use OZONE\OZ\Exceptions\OZoneNotFoundException;
	use OZONE\OZ\FS\OZoneImagesUtils;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	class FilesServer extends OZoneService {

		/**
		 * FilesServer constructor.
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * {@inheritdoc}
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneInvalidFormException
		 */
		public function execute( $request = array() ) {
			OZoneAssert::assertForm( $request, array( 'src', 'fname', 'fmime', 'thumb' ), new OZoneNotFoundException() );

			// désactive le temps max d'exécution
			set_time_limit( 0 );

			// désactivation compression GZip
			/*
			if (ini_get( "zlib.output_compression" ) )
			{
				ini_set( "zlib.output_compression", 'Off' );
			}
			*/

			$src = $request[ 'src' ];
			$fname = $request[ 'fname' ];
			$fmime = $request[ 'fmime' ];
			$thumb = $request[ 'thumb' ];
			$size = filesize( $src );

			//$fmime = "application/force-download";//<-- par defaut
			//fermeture de la session
			session_write_close();

			if ( ob_get_contents() )
				ob_clean();

			header( 'Pragma: public' ); //requis
			header( 'Expires: 99936000' );
			header( 'Cache-Control: public, max-age=99936000' );//requis
			header( 'Content-Transfer-Encoding: binary' );
			header( "Content-Length: $size" );
			header( "Content-type: $fmime" );
			header( "Content-Disposition: attachment; filename='$fname';" );

			//envoi le contenu du fichier
			if ( $thumb > 0 ) {
				//thumbnails
				//0: 'original file'
				//1: 'low quality',
				//2: 'normal quality',
				//3: 'high quality'
				$jpeg_quality_array = array( 60, 80, 100 );
				$jpeg_quality = $jpeg_quality_array[ $thumb - 1 ];
				$img_utils_obj = new OZoneImagesUtils( $src );

				//ceci 'devrait' etre toujours vrai
				if ( $img_utils_obj->load() ) {
					$max_size = OZoneSettings::get( 'oz.user', 'OZ_THUMB_MAX_SIZE' );
					$advice = $img_utils_obj->adviceBestSize( $max_size, $max_size );
					// $img_r = imagecreatefromjpeg( $src );
					// $src_w = imagesx( $img_r );
					// $src_h = imagesy( $img_r );
					// $targ_w = $advice[ 'w' ];
					// $targ_h = $advice[ 'h' ];
					// $dest_r = imagecreatetruecolor( $targ_w, $targ_h );
					// imagecopyresampled( $dest_r, $img_r, 0, 0, 0, 0, $targ_w, $targ_h, $src_w, $src_h );

					// header( 'Content-type: image/jpeg' );
					// //important car la taille changera a cause de la compresion
					// header_remove( 'Content-Length' );

					// imagejpeg( $dest_r, null, $jpeg_quality );

					// //free memory
					// imagedestroy( $img_r );
					// imagedestroy( $dest_r );
					$img_utils_obj
						->resizeImage( $advice[ 'w' ], $advice[ 'h' ], $advice[ 'crop' ] )
						->outputJpeg( $jpeg_quality );

				} else {
					//on affiche le thumb tel qu'il est
					$this->sendOriginal( $src );//en suivant la logique il s'agit bien du thumbnail
				}
			} else {
				$this->sendOriginal( $src );
			}
		}

		/**
		 * send a file at a given path to the client
		 *
		 * @param string $path the file path
		 */
		private function sendOriginal( $path ) {
			flush();
			readfile( $path );
			exit;
		}

		/**
		 * starts a file download server
		 *
		 * @param array $options      the server options
		 * @param bool  $allow_resume should we support download resuming
		 * @param bool  $is_stream    is it a stream
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneInternalError
		 * @throws \OZONE\OZ\Exceptions\OZoneUnauthorizedActionException
		 */
		public static function startDownloadServer( array $options, $allow_resume = false, $is_stream = false ) {
			//- turn off compression on the server
			@apache_setenv( 'no-gzip', 1 );
			@ini_set( 'zlib.output_compression', 'Off' );

			$mime_default = "application/octet-stream";
			$file_path = $options[ 'path' ];
			$file_name = isset( $options[ 'name' ] ) ? $options[ 'name' ] : basename( $file_path );
			$expires_date = isset( $options[ 'expires_date' ] ) ? $options[ 'expires_date' ] : -1;
			$mime = isset( $options[ 'mime' ] ) ? $options[ 'mime' ] : $mime_default;
			$range = '';

			// make sure the file exists
			OZoneAssert::assertAuthorizeAction( is_file( $file_path ), new OZoneNotFoundException() );

			$file_size = filesize( $file_path );
			$file = @fopen( $file_path, 'rb' );

			// make sure file open success
			if ( !$file )
				throw new OZoneInternalError( null, array( "can't open file at $file_path" ) );

			// set the headers, prevent caching
			header( 'Pragma: public' );
			header( 'Expires: ' . $expires_date );
			header( 'Cache-Control: public, max-age=' . $expires_date . ', must-revalidate, post-check=0, pre-check=0' );

			// set appropriate headers for attachment or streamed file
			if ( !$is_stream ) {
				header( "Content-Disposition: attachment; filename='$file_name';" );
			} else {
				header( 'Content-Disposition: inline;' );
				header( 'Content-Transfer-Encoding: binary' );
			}

			// set the mime type
			header( "Content-Type: " . $mime );

			//check if http_range is sent by browser (or download manager)
			if ( $allow_resume AND isset( $_SERVER[ 'HTTP_RANGE' ] ) ) {
				list( $size_unit, $range_orig ) = explode( '=', $_SERVER[ 'HTTP_RANGE' ], 2 );
				if ( $size_unit === 'bytes' ) {
					//multiple ranges could be specified at the same time, but for simplicity only serve the first range
					//http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
					@list( $range ) = explode( ',', $range_orig, 2 );
				} else {
					header( 'HTTP/1.1 416 Requested Range Not Satisfiable' );
					exit;
				}
			}

			//figure out download piece from range (if set)
			@list( $seek_start, $seek_end ) = explode( '-', $range, 2 );

			//set start and end based on range (if set), else set defaults
			//also check for invalid ranges.
			$seek_end = ( empty( $seek_end ) ) ? ( $file_size - 1 ) : min( abs( intval( $seek_end ) ), ( $file_size - 1 ) );
			$seek_start = ( empty( $seek_start ) || $seek_end < abs( intval( $seek_start ) ) ) ? 0 : max( abs( intval( $seek_start ) ), 0 );

			//Only send partial content header if downloading a piece of the file (IE workaround)
			if ( $seek_start > 0 || $seek_end < ( $file_size - 1 ) ) {
				$chunk_size = ( $seek_end - $seek_start + 1 );

				header( 'HTTP/1.1 206 Partial Content' );
				header( 'Content-Range: bytes ' . $seek_start . '-' . $seek_end . '/' . $file_size );
				header( "Content-Length: $chunk_size" );
			} else {
				header( "Content-Length: $file_size" );
			}

			header( 'Accept-Ranges: bytes' );

			set_time_limit( 0 );
			fseek( $file, $seek_start );

			while ( !feof( $file ) ) {
				print( @fread( $file, 1024 * 8 ) );
				ob_flush();
				flush();
				if ( connection_status() != 0 ) {
					@fclose( $file );
					exit;
				}
			}

			// file download was a success
			@fclose( $file );
			exit;
		}
	}