<?php

	class OPathResolver {
		const DS = DIRECTORY_SEPARATOR;

		public static function resolve( $root, $path ) {
			$root = empty( $root ) ? __DIR__ : self::nomalize( $root );
			$path = self::nomalize( $path );

			if ( self::isRelative( $path ) ) {
				if ( ( self::DS === '/' AND $path[ 0 ] === '/' ) OR preg_match( "#^[\w]+:#", $path ) ) {
					/*$path already start form the root
						/ 	of linux - unix
						D: 	of windows
					*/
					$full_path = $path;
				} else {
					$full_path = $root . self::DS . $path;
				}

				return self::job( explode( self::DS, $full_path ) );

			} else {
				return $path;
			}
		}

		private static function job( array $in ) {
			$out = array();

			foreach ( $in as $part ) {
				//ignore part that have no value
				if ( empty( $part ) OR $part === '.' )
					continue;

				if ( $part !== '..' ) {
					//cool we found a new part
					array_push( $out, $part );
				} else if ( count( $out ) > 0 ) {
					//going back up? sure
					array_pop( $out );
				} else {
					//now here we don't like
					throw new Exception( "Climbing above root is dangerouse: " . join( self::DS, $in ) );
				}
			}

			return join( self::DS, $out );
		}

		public static function nomalize( $path ) {
			if ( self::DS == "\\" )
				return strtr( $path, '/', '\\' );

			return strtr( $path, '\\', '/' );
		}

		public static function isRelative( $path ) {
			return preg_match( "#^\.{1,2}[/\\\\]?#", $path )
			OR preg_match( "#[/\\\\]\.{1,2}[/\\\\]#", $path )
			OR preg_match( "#[/\\\\]\.{1,2}$#", $path );
		}
	}