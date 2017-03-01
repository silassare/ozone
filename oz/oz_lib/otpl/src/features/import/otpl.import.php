<?php

	final class OTplFeature_Import {
		//SILO:: @import(url [,data]) replacement
		const REG = "#@import\([\s]*?(?:'|\")(.*?)(?:'|\")(?:[\s]*,[\s]*(.+?)[\s]*)?[\s]*\)#";

		public static function exec( $in, OTpl $context ) {
			$url = $in[ 1 ];

			$data = isset( $in[ 2 ] ) ? $in[ 2 ] : null;

			return self::_import( $url, $data, $context );
		}

		private static function _import( $url, $data_str, OTpl $context ) {
			$src_dir = $context->getSrcDir();
			$root = ( $src_dir ) ? $src_dir : OTPL_ROOT_DIR;

			$url = OPathResolver::resolve( $root, $url );

			if ( OTplUtils::isTplFile( $url ) ) {
				return " OTplUtils::importExec( '$url', $data_str ) ";
			} else {
				return " OTplUtils::loadFile( '$url' ) ";
			}
		}
	}

	OTplUtils::addReplacer( OTplFeature_Import::REG, array( 'OTplFeature_Import', 'exec' ) );