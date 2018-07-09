<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Loader;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * OZone class loader comply with PSR-4.
	 * Used to load:
	 *    - fully-qualified class.
	 *  - and class which are not in a namespace context.
	 *
	 *```
	 * /root/
	 *        app/
	 *            abo/
	 *                Utils.php
	 *            lib/
	 *                Bar.php
	 *                sub/
	 *                    Baz.php
	 *            test/
	 *                baz/
	 *                    BazTest.php
	 *                foo/
	 *                    bar/
	 *                        FooBarTest.php
	 *        packages/
	 *            Foo/
	 *                Bar/
	 *                    BarClass.php             # Foo\Bar\BarClass
	 *                Baz/
	 *                    BazClass.php             # Foo\Baz\BazClass
	 *            FooZoo/
	 *                ZooClass.php                 # Foo\Zoo\ZooClass
	 *            OZone/
	 *                Loader/
	 *                    ClassLoader.php
	 *        index.php
	 *```
	 *
	 *```php
	 * <?php
	 *  // include the class loader file
	 *    require_once "/root/packages/OZone/Loader/ClassLoader.php" ;
	 *
	 *    // no need to instantiate the loader nor register class loader
	 *    $loader = \OZONE\OZ\Loader\ClassLoader;
	 *
	 *    // to load class with namespace
	 *        // register the base directories for your namespace prefixes
	 *        $loader::addNamespace('Foo\Bar', '/root/packages/Foo/Bar');
	 *        $loader::addNamespace('Foo\Baz', '/root/packages/Foo/Baz');
	 *        $loader::addNamespace('Foo\Zoo', '/root/packages/FooZoo');
	 *
	 *    // to load namespace-less class
	 *        // add the path to the class files
	 *    $loader::add( '/root/app/abo/' );
	 *    $loader::add( '/root/app/lib/', true, 1 );
	 *    $loader::add( '/root/app/test/', true, 2 );
	 *
	 *        // or you could use this
	 *    $loader::addDirs( array(
	 *            '/root/abo/',
	 *            array( '/root/app/lib/', true, 1 ),
	 *        array( '/root/app/test/', true, 2 )
	 *        ) );
	 *
	 *        // or go the fast way
	 *    $loader::addDir( '/root/app/' , true, 3 );
	 *```
	 */
	class ClassLoader
	{
		/**
		 * Class name regexp
		 *
		 * class name must:
		 *        - start with a CapitalLetter
		 *        - minimum length of 1
		 *        - only alphanumerics and _ are allowed
		 *
		 * @var string Class file name regular expression
		 */
		const CLASS_FILE_REG = "#([A-Z][a-zA-Z0-9_]*)\.php$#";

		/**
		 * Cache array of indexed dirs.
		 *
		 * @var array
		 */
		private static $indexed_dirs = [];

		/**
		 * Array of classes name mapped to classes path.
		 *
		 * @var array
		 */
		private static $class_map = [];

		/**
		 * An associative array where the key is a namespace prefix and the value
		 * is an array of base directories for classes in that namespace.
		 *
		 * @var array
		 */
		protected static $psr4_namespaces_map = [];

		/**
		 * Registered as class loader or not.
		 *
		 * @var bool
		 */
		private static $registered = false;

		/**
		 * Register loader with SPL autoloader stack.
		 *
		 * @return void
		 */
		protected static function register()
		{
			if (self::$registered === false) {
				self::$registered = true;
				spl_autoload_register([self::class, 'loadClass']);
			}
		}

		/**
		 * Adds directory to the indexed directories list.
		 *
		 * @param string $dir       The directory path.
		 * @param bool   $recursive Define if we should index sub directories.
		 * @param int    $deep      Define how deep we should go if recursive.
		 *
		 * @throws \Exception
		 */
		public static function addDir($dir, $recursive = false, $deep = 1)
		{
			// let's register our class loader if not done
			self::register();

			$dir = self::cleanPath($dir);

			if (!is_dir($dir)) {
				throw new \Exception(sprintf('"%s" does not exists or is not a directory.', $dir));
			}

			if (in_array($dir, self::$indexed_dirs)) {
				return;
			}

			array_push(self::$indexed_dirs, $dir);

			$res = opendir($dir);

			if ($res) {
				while (false !== ($filename = readdir($res))) {
					if ($filename !== '.' AND $filename !== '..') {
						$c_path = $dir . DIRECTORY_SEPARATOR . $filename;
						$in     = [];

						if (is_file($c_path) AND preg_match(ClassLoader::CLASS_FILE_REG, $filename, $in)) {
							$class_name = $in[1];

							if (!array_key_exists($class_name, self::$class_map)) {
								self::$class_map[$class_name] = $c_path;
							}
						} elseif (!!$recursive AND $deep > 0 AND is_dir($c_path)) {
							self::addDir($c_path, $recursive, $deep - 1);
						}
					}
				}

				closedir($res);
			} else {
				throw new \Exception(sprintf('cannot open directory at: "%s"', $dir));
			}
		}

		/**
		 * Adds directories to the indexed directories list.
		 *
		 * @param array $dirs      The directories list name.
		 * @param bool  $recursive Define if we should index sub directories.
		 * @param int   $deep      Define how deep we should go if recursive.
		 *
		 * @return void
		 * @throws \Exception
		 */
		public static function addDirs(array $dirs, $recursive = false, $deep = 1)
		{
			foreach ($dirs as $dir) {
				if (is_array($dir)) {
					$c_dir       = isset($dir[0]) ? $dir[0] : null;
					$c_recursive = isset($dir[1]) ? $dir[1] : $recursive;
					$c_deep      = isset($dir[2]) ? $dir[2] : $deep;

					self::addDir($c_dir, $c_recursive, $c_deep);
				} else {
					self::addDir($dir, $recursive, $deep);
				}
			}
		}

		/**
		 * Adds a base directory for a namespace prefix.
		 *
		 * @param string $prefix   The namespace prefix.
		 * @param string $base_dir A base directory for class files in the
		 *                         namespace.
		 * @param bool   $prepend  If true, prepend the base directory to the stack
		 *                         instead of appending it; this causes it to be searched first rather
		 *                         than last.
		 *
		 * @throws \Exception
		 */
		public static function addNamespace($prefix, $base_dir, $prepend = false)
		{
			// let's register our class loader if not done
			self::register();

			$dir = self::cleanPath($base_dir);

			if (!is_dir($dir)) {
				throw new \Exception(sprintf('"%s" does not exists or is not a directory.', $dir));
			}

			// normalize namespace prefix
			$prefix = trim($prefix, '\\') . '\\';
			// normalize the base directory with a trailing separator
			$base_dir = rtrim($base_dir, DIRECTORY_SEPARATOR) . '/';
			// initialize the namespace prefix array
			if (isset (self::$psr4_namespaces_map[$prefix]) === false) {
				self::$psr4_namespaces_map[$prefix] = [];
			}

			if (!in_array($base_dir, self::$psr4_namespaces_map[$prefix])) {
				// retain the base directory for the namespace prefix
				if ($prepend) {
					array_unshift(self::$psr4_namespaces_map[$prefix], $base_dir);
				} else {
					array_push(self::$psr4_namespaces_map[$prefix], $base_dir);
				}
			}
		}

		/**
		 * Loads the class file for a given class name fully-qualified or not.
		 *
		 * @param string $class_name The class name.
		 *
		 * @return mixed The mapped file name on success, or boolean false on failure.
		 */
		public static function loadClass($class_name)
		{
			if (is_string($class_name)) {
				if (false !== strrpos($class_name, '\\')) {
					// it seems to be a fully-qualified class name
					return self::loadClassPsr4($class_name);
				} elseif (array_key_exists($class_name, self::$class_map)) {
					$path = self::$class_map[$class_name];

					if (self::requireFile($path)) {
						return $path;
					}
				}
			}

			// class not found
			return false;
		}

		/**
		 * Loads the class file for a given fully-qualified class name.
		 *
		 * @param string $class The fully-qualified class name.
		 *
		 * @return mixed The mapped file name on success, or boolean false on failure.
		 */
		protected static function loadClassPsr4($class)
		{
			// the current namespace prefix
			$prefix = $class;
			// work backwards through the namespace names of the fully-qualified
			// class name to find a mapped file name
			while (false !== $pos = strrpos($prefix, '\\')) {
				// retain the trailing namespace separator in the prefix
				$prefix = substr($class, 0, $pos + 1);
				// the rest is the relative class name
				$relative_class = substr($class, $pos + 1);
				// try to load a mapped file for the prefix and relative class
				$mapped_file = self::getPsr4MappedFile($prefix, $relative_class);

				if ($mapped_file) {
					return $mapped_file;
				}
				// remove the trailing namespace separator for the next iteration
				$prefix = rtrim($prefix, '\\');
			}

			// never found a mapped file
			return false;
		}

		/**
		 * Load the mapped file for a namespace prefix and relative class.
		 *
		 * @param string $prefix         The namespace prefix.
		 * @param string $relative_class The relative class name.
		 *
		 * @return mixed Boolean false if no mapped file can be loaded, or the
		 * name of the mapped file that was loaded.
		 */
		protected static function getPsr4MappedFile($prefix, $relative_class)
		{
			// are there any base directories for this namespace prefix?
			if (isset (self::$psr4_namespaces_map[$prefix]) === false) {
				return false;
			}
			// look through base directories for this namespace prefix
			foreach (self::$psr4_namespaces_map[$prefix] as $base_dir) {
				// replace the namespace prefix with the base directory,
				// replace namespace separators with directory separators
				// in the relative class name, append with .php
				$file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
				// if the mapped file exists, require it
				if (self::requireFile($file)) {
					// yes, we're done
					return $file;
				}
			}

			// never found it
			return false;
		}

		/**
		 * Checks if the class exists
		 *
		 * @param string $class_name The class name
		 *
		 * @return bool
		 */
		public static function exists($class_name)
		{
			if (is_string($class_name)) {
				self::loadClass($class_name);

				return class_exists($class_name);
			}

			return false;
		}

		/**
		 * Clean a given directory path.
		 *
		 * @param string $dir_path The directory path.
		 *
		 * @return string
		 */
		protected static function cleanPath($dir_path)
		{
			if (is_string($dir_path) AND strlen($dir_path) > 1) {
				// remove last / or \
				$dir_path = rtrim($dir_path, '\\/');

				if (DIRECTORY_SEPARATOR === '\\') {
					$dir_path = str_replace('/', '\\', $dir_path);
				}
			}

			return $dir_path;
		}

		/**
		 * If a file exists, require it from the file system.
		 *
		 * @param string $file The file to require.
		 *
		 * @return bool True if the file exists, false if not.
		 */
		protected static function requireFile($file)
		{
			if (file_exists($file)) {
				require $file;

				return true;
			}

			return false;
		}

		/**
		 * Create instance of class for a given class name and arguments array list.
		 *
		 * @param string $class_name The class name.
		 * @param array  $args       A list of arguments, used to instantiate.
		 *
		 * @return object
		 * @throws \ReflectionException
		 */
		public static function instantiateClass($class_name, $args = [])
		{
			$obj = new \ReflectionClass($class_name);

			return $obj->newInstanceArgs($args);
		}
	}