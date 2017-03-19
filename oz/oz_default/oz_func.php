<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	function oz_file_read( $src ) {
		if ( empty( $src ) || !file_exists( $src ) || !is_file( $src ) || !is_readable( $src ) ) {
			throw new Exception( "Unable to access file at : $src" );
		}

		return file_get_contents( $src );
	}

	function oz_logger( $in ) {
		$log = '';

		if ( $in instanceof \OZONE\OZ\Exceptions\OZoneBaseException ) {
			$log .= $in;
		} elseif ( $in instanceof Exception ) {
			$e = $in;
			$log = "\n\tFile    : {$e->getFile()}"
				. "\n\tLine    : {$e->getLine()}"
				. "\n\tCode    : {$e->getCode()}"
				. "\n\tMessage : {$e->getMessage()}"
				. "\n\tTrace   : {$e->getTraceAsString()}";
		} else {
			$log = $in;
		}

		$txt = trim( json_encode( $log ), '"' );
		$txt = str_replace( array( "\\n", "\\t", "\\\\" ), array( "\n", "\t", "\\" ), $txt );
		$txt = date( 'Y-m-d H:i:s' )
			. ( OZ_APP_DEBUG_MODE ? ' > OZ_APP_DEBUG_MODE' : '' )
			. "\n"
			. $txt
			. "\n================================================================================\n";

		$f = fopen( OZ_ROOT_DIR . 'debug.log', 'a+' );
		fwrite( $f, $txt );
		fclose( $f );
	}

	function oz_exception_handler( $e ) {

		oz_logger( $e );

		$mask_e = new \OZONE\OZ\Exceptions\OZoneInternalError( 'Internal error: unhandled, if you are an admin please look on log file and fix it!' );
		$mask_e->procedure();

		false OR die;
	}

	function oz_error_handler( $code, $message, $file, $line ) {

		$log = "\n\tFile    : {$file}"
			. "\n\tLine    : {$line}"
			. "\n\tCode    : {$code}"
			. "\n\tMessage : {$message}";

		oz_logger( $log );
	}

	set_exception_handler( 'oz_exception_handler' );
	set_error_handler( 'oz_error_handler' );