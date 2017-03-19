<?php

	include OTPL_SRC_DIR . 'OPathResolver.php';
	include OTPL_SRC_DIR . 'OTplData.php';
	include OTPL_SRC_DIR . 'OTplUtils.php';

	function otpl_make_include( $dir ) {

		if ( DIRECTORY_SEPARATOR == "\\" ) {
			$dir = strtr( $dir, '/', '\\' );
		}

		if ( file_exists( $dir ) AND is_dir( $dir ) AND $res = @opendir( $dir ) ) {

			while ( false !== ( $filename = readdir( $res ) ) ) {

				if ( $filename !== '.' AND $filename !== '..' ) {

					$c_path = $dir . DIRECTORY_SEPARATOR . $filename;

					if ( is_dir( $c_path ) ) {

						$dname = $filename;
						$index = $c_path . DIRECTORY_SEPARATOR . 'otpl.' . $dname . '.php';

						if ( file_exists( $index ) AND is_file( $index ) ) {
							require_once $index;
						}
					}
				}
			}
		} else {
			throw new Exception( "otpl_make_include > `$dir` not found or is not a valid directory." );
		}
	}

	otpl_make_include( OTPL_SRC_DIR . '/features' );
	otpl_make_include( OTPL_SRC_DIR . '/plugins' );