<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	class OZoneClassLoader {
		const CLASS_FILE_REG = "#([A-Z]{2}[a-zA-Z0-9]+)\.php$#";

		private static $class_dirs = array();
		private static $class_map = array();
		private static $registred = false;

		private static function cleanPath( $path ) {

			if ( strlen( $path ) >= 2 ) {
				//remove last / or \
				$path = preg_replace( "#(/|\\\\)$#", '', $path );
			}

			if ( DIRECTORY_SEPARATOR == "\\" ) {
				//$path = strtr( $path , '/' , "\\" );
			}

			return $path;
		}

		public static function addDir( $dir, $recursive = false, $deep = 1 ) {
			//let's register our class loader if not done
			if ( self::$registred === false ) {
				self::$registred = true;
				spl_autoload_register( array( 'OZoneClassLoader', 'loadClass' ) );
			}

			$dir = self::cleanPath( $dir );

			if ( !file_exists( $dir ) || !is_dir( $dir ) ) {
				throw new Exception( "'$dir' does not exists or is not a directory" );
			}

			if ( in_array( $dir, self::$class_dirs ) ) {
				return;
			}

			array_push( self::$class_dirs, $dir );

			$res = opendir( $dir );

			if ( $res ) {

				while ( false !== ( $filename = readdir( $res ) ) ) {

					if ( $filename !== '.' AND $filename !== '..' ) {

						$c_path = $dir . DIRECTORY_SEPARATOR . $filename;
						$in = array();

						if ( is_file( $c_path ) AND preg_match( OZoneClassLoader::CLASS_FILE_REG, $filename, $in ) ) {

							$class_name = $in[ 1 ];
							self::$class_map[ $class_name ] = $c_path;

						} elseif ( !!$recursive AND $deep > 0 AND is_dir( $c_path ) ) {
							self::addDir( $c_path, $recursive, $deep - 1 );
						}
					}
				}
			} else {
				throw new Exception( "Can't open directory at: '$dir'" );
			}

		}

		public static function addDirsList( array $dirs, $recursive = false, $deep = 1 ) {
			foreach ( $dirs as $dir ) {
				self::addDir( $dir, $recursive );
			}
		}

		private static function getClassPath( $class_name ) {
			if ( array_key_exists( $class_name, self::$class_map ) ) {
				return self::$class_map[ $class_name ];
			}

			return null;
		}

		public static function exists( $class_name ) {

			self::loadClass( $class_name );

			return class_exists( $class_name );
		}

		public static function loadClass( $class_name ) {
			$path = self::getClassPath( $class_name );

			if ( $path )
				require_once( $path );
		}

		public static function instanciateClass( $class_name, $args = array() ) {
			if ( self::exists( $class_name ) ) {
				$obj = new ReflectionClass( $class_name );

				return $obj->newInstanceArgs( $args );
			}

			throw new Exception( "Oops Class '$class_name' not found!" );
		}
	}
