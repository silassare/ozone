<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	function oz_file_read( $src ) {
		if ( empty( $src ) || !file_exists( $src ) || !is_file( $src ) || !is_readable( $src ) ) {
			throw new Exception( "Unable to access file at : $src" );
		}

		return file_get_contents( $src );
	}

	function oz_logger( $data ) {
		$txt = trim( json_encode( $data ), '"' );
		$txt = str_replace( array( "\\n", "\\t", "\\\\" ), array( "\n", "\t", "\\" ), $txt );
		$txt = date( 'Y-m-d H:i:s' )
			. ( OZ_DEBUG_MODE ? ' > OZ_DEBUG_MODE' : '' )
			. "\n"
			. $txt
			. "\n\n";

		$f = fopen( OZ_ROOT_DIR . 'debug.log', 'a+' );
		fwrite( $f, $txt );
		fclose( $f );
	}

	function oz_error_handler( $e ) {

		oz_logger( $e->getMessage() );

		if ( $e instanceof OZoneError ) {
			oz_logger( $e->getErrorData() );
		}

		$oe = new OZoneErrorInternalError( 'Internal error: unhandled, if you are an admin please look on log file and fix it!' );
		$oe->procedure();

		false OR die;
	}

	set_exception_handler( 'oz_error_handler' );