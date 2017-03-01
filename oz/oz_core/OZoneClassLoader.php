<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	/**
	 * OZone class loader not comply with PSR-0 nor PSR-4.
	 * Used to load class wich are not in a namespace context.
	 *
	 *```
	 * /path/to/root/
	 *		abo/
	 *			Utils.php
	 *		lib/
	 *			Bar.php
	 *			sub/
	 *				Baz.php
	 *		test/
	 *			baz/
	 *				BazTest.php
	 *			foo/
	 *				bar/
	 * 					FooBarTest.php
	 *		OZoneClassLoader.php
	 *	  	index.php
	 *```
	 *
	 *```php
	 * <?php
	 *  // include the class loader file
	 *	require_once "OZoneClassLoader.php" ;
	 *
	 *	// add the path to the class files
	 *	// no need to instantiate the loader or register
	 *
	 *  OZoneClassLoader::add( './abo/' );
	 *  OZoneClassLoader::add( './lib/', true, 1 );
	 *  OZoneClassLoader::add( './test/', true, 2 );
	 *
	 *	// or you could use this
	 *
	 *  OZoneClassLoader::addDirs( array(
	 *		'./abo/',
	 *		array( './lib/', true, 1 ),
	 *  	array( './test/', true, 2 )
	 *  ) );
	 *
	 *	// or go the fast way
	 *			
	 *  OZoneClassLoader::addDir( './' , true, 3 );
	 *```
	 */

	class OZoneClassLoader {
		/**
		 * Class name regexp
		 *
		 * class name must:
		 * 		- start with a CapitalLetter
		 *		- minimum length of 1
		 *		- only alphanumerics and _ are allowed
		 *
		*/

		const CLASS_FILE_REG = "#([A-Z][a-zA-Z0-9_]*)\.php$#";

		/**
		 * Cache array of indexed dirs.
		 *
		 * @var array
		 */

		private static $indexed_dirs = array();

		/**
		 * Array of classes name mapped to classes path.
		 *
		 * @var array
		 */

		private static $class_map = array();

		/**
		 * An associative array where the key is a namespace prefix and the value
		 * is an array of base directories for classes in that namespace.
		 *
		 * @var bool
		 */

		private static $registred = false;

		/**
		 * Add directory to the indexed directories list.
		 *
		 * @param string $dir 		The directory path.
		 * @param bool $recursive 	Define if we should index sub directories.
		 * @param int $deep 		Define how deep we should go if recursive.
		 *
		 * @return void
		 */

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

			if ( in_array( $dir, self::$indexed_dirs ) ) {
				return;
			}

			array_push( self::$indexed_dirs, $dir );

			$res = opendir( $dir );

			if ( $res ) {

				while ( false !== ( $filename = readdir( $res ) ) ) {

					if ( $filename !== '.' AND $filename !== '..' ) {

						$c_path = $dir . DIRECTORY_SEPARATOR . $filename;
						$in = array();

						if ( is_file( $c_path ) AND preg_match( OZoneClassLoader::CLASS_FILE_REG, $filename, $in ) ) {

							$class_name = $in[ 1 ];

							if( !array_key_exists( $class_name, self::$class_map ) ) {
								self::$class_map[ $class_name ] = $c_path;
							} else {
								$class_path = self::$class_map[ $class_name ];
								throw new Exception( "$class_name defined in $class_path Can't overwrite with $c_path" );
							}

						} elseif ( !!$recursive AND $deep > 0 AND is_dir( $c_path ) ) {
							self::addDir( $c_path, $recursive, $deep - 1 );
						}
					}
				}

			} else {
				throw new Exception( "Can't open directory at: $dir" );
			}

		}

		/**
		 * Add directories to the indexed directories list.
		 *
		 * @param array $dirs 		The directories list name.
		 * @param bool $recursive 	Define if we should index sub directories.
		 * @param int $deep 		Define how deep we should go if recursive.
		 *
		 * @return void
		 */

		public static function addDirs( array $dirs, $recursive = false, $deep = 1 ) {

			foreach ( $dirs as $dir ) {

				if ( is_array( $dir ) ) {
					$c_dir 		 = isset( $dir[0] ) ? $dir[0] : null;
					$c_recursive = isset( $dir[1] ) ? $dir[1] : $recursive;
					$c_deep		 = isset( $dir[2] ) ? $dir[2] : $deep;

					self::addDir( $c_dir, $c_recursive, $c_deep );
				} else {
					self::addDir( $dir, $recursive, $deep );
				}
			}
		}

		/**
		 * Return class path for a given class name.
		 *
		 * @param string $class_name The class name.
		 *
		 * @return string|null
		 */

		private static function getClassPath( $class_name ) {
			if ( array_key_exists( $class_name, self::$class_map ) ) {
				return self::$class_map[ $class_name ];
			}

			return null;
		}

		/**
		 * Check if the class exists
		 *
		 * @param string $class_name The class name
		 *
		 * @return bool
		 */

		public static function exists( $class_name ) {

			self::loadClass( $class_name );

			return class_exists( $class_name );
		}

		/**
		 * Loads the class file for a given class name.
		 *
		 * @param string $class_name The class name.
		 *
		 * @return void
		 */

		public static function loadClass( $class_name ) {
			$path = self::getClassPath( $class_name );

			if ( !empty( $path ) ) {
				require_once $path ;
			}
		}

		/**
		 * Clean a given directory path.
		 *
		 * @param string $dir_path 	The directory path.
		 *
		 * @return string
		 */

		private static function cleanPath( $dir_path ) {

			if ( is_string( $dir_path ) AND strlen( $dir_path ) > 1 ) {
				//remove last / or \
				$dir_path = rtrim( $dir_path, '\\/' );

				if ( DIRECTORY_SEPARATOR === '\\' ) {
					$dir_path = str_replace('/', '\\', $dir_path);
				}
			}

			return $dir_path;
		}

		/**
		 * Create instance of class for a given class name and arguments.
		 *
		 * @param string $class_name The class name.
		 * @param array $args 		 A list of arguments, used to instantiate.
		 *
		 * @return mixed
		 */

		public static function instantiateClass( $class_name, $args = array() ) {

			if ( self::exists( $class_name ) ) {
				$obj = new ReflectionClass( $class_name );

				return $obj->newInstanceArgs( $args );
			}

			throw new Exception( "Oops Class '$class_name' not found!" );
		}
	}