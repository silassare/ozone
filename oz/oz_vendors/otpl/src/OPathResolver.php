<?php

	class OPathResolver {
		const DS = DIRECTORY_SEPARATOR;

		public static function resolve( $root, $path ) {
			$root = self::normalize( $root );
			$path = self::normalize( $path );

			if ( self::isRelative( $path ) ) {
				if ( ( self::DS === '/' AND $path[ 0 ] === '/' ) OR preg_match( "#^[\w]+:#", $path ) ) {
					/*$path start form the root
						/ 	of linux - unix
						D: 	of windows
					*/
					$full_path = $path;
				} else {
					$full_path = $root . self::DS . $path;
				}

				return self::job( $full_path );

			} else {
				return $path;
			}
		}

		private static function job( $src ) {
			$in = explode( self::DS, $src );
			$out = array();

			//preserve linux root first char '/' like in: /root/path/to/
			if ( $src[ 0 ] === self::DS ) {
				array_push( $out, '' );
			}

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
					throw new Exception( "Climbing above root is dangerouse: " . $src );
				}
			}

			if ( !count( $out ) ) {
				return self::DS;
			}

			if ( count( $out ) === 1 ) {
				array_push( $out, null );
			}

			return join( self::DS, $out );
		}

		public static function normalize( $path ) {
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