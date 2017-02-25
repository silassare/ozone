<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	class OZoneServiceFilesServer extends OZoneService {

		public function __construct() {
			parent::__construct();
		}

		public function execute( $request = array() ) {
			OZoneAssert::assertForm( $request, array( 'src', 'fname', 'fmime', 'thumb' ), new OZoneErrorNotFound() );

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
				//0: 'Original file'
				//1: 'PARAM_IMAGE_QUALITE_FAIBLE',
				//2: 'PARAM_IMAGE_QUALITE_MOYENNE',
				//3: 'PARAM_IMAGE_QUALITE_ELEVEE'
				$jpeg_quality_array = array( 60, 80, 100 );
				$jpeg_quality = $jpeg_quality_array[ $thumb - 1 ];
				$ui = OZone::obj( 'OZoneImagesUtils', $src );

				//ceci 'devrait' etre toujours vrai
				if ( $ui->load() ) {
					$wh = OZoneSettings::get( 'oz.user', 'OZ_THUMB_MAX_SIZE' );
					$advice = $ui->adviceBestSize( $wh, $wh );
					$img_r = imagecreatefromjpeg( $src );
					$src_w = imagesx( $img_r );
					$src_h = imagesy( $img_r );
					$targ_w = $advice[ 'w' ];
					$targ_h = $advice[ 'h' ];
					$dest_r = imagecreatetruecolor( $targ_w, $targ_h );
					imagecopyresampled( $dest_r, $img_r, 0, 0, 0, 0, $targ_w, $targ_h, $src_w, $src_h );

					header( 'Content-type: image/jpeg' );
					//important car la taille changera a cause de la compresion
					header_remove( 'Content-Length' );

					imagejpeg( $dest_r, null, $jpeg_quality );

					//free memory
					imagedestroy( $img_r );
					imagedestroy( $dest_r );

				} else {
					//on affiche le thumb tel qu'il est
					$this->sendOriginale( $src );//en suivant la logique il s'agit bien du thumbnail
				}
			} else {
				$this->sendOriginale( $src );
			}
		}

		private function sendOriginale( $src ) {
			flush();
			readfile( $src );
		}

		public static function startDownloadServer( $options, $allow_resume = false, $is_stream = false ) {
			//- turn off compression on the server
			@apache_setenv( 'no-gzip', 1 );
			@ini_set( 'zlib.output_compression', 'Off' );

			$mime_default = "application/octet-stream";
			$file_path = $options[ 'path' ];
			$file_name = isset( $options[ 'name' ] ) ? $options[ 'name' ] : basename( $file_path );
			$expires_date = isset( $options[ 'expires_date' ] ) ? $options[ 'expires_date' ] : -1;
			$mime = isset( $options[ 'mime' ] ) ? $options[ 'mime' ] : $mime_default;

			// make sure the file exists
			OZoneAssert::assertAuthorizeAction( is_file( $file_path ), new OZoneErrorNotFound() );

			$file_size = filesize( $file_path );
			$file = @fopen( $file_path, 'rb' );

			// make sure file open success
			OZoneAssert::assertAuthorizeAction( $file, new OZoneErrorInternalError() );

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
					@list( $range, $extra_ranges ) = explode( ',', $range_orig, 2 );
				} else {
					$range = '';
					header( 'HTTP/1.1 416 Requested Range Not Satisfiable' );
					exit;
				}
			} else {
				$range = '';
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