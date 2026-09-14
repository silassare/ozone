<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace OZONE\Core\Loader;

use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * OZone class loader comply with PSR-4.
 * Used to load:
 *    - fully-qualified class.
 *  - and class which are not in a namespace context.
 *loadClass
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
 *```.
 *
 *```php
 * <?php
 *  // include the class loader file
 *    require_once "/root/packages/OZone/Loader/ClassLoader.php" ;
 *
 *    // no need to instantiate the loader nor register class loader
 *    $loader = \OZONE\Core\Loader\ClassLoader;
 *
 *    // to load class with namespace
 *        // register the base directories for your namespace prefixes
 *        $loader::addNamespace('Foo\Bar', '/root/packages/Foo/Bar');
 *        $loader::addNamespace('Foo\Baz', '/root/packages/Foo/Baz');
 *        $loader::addNamespace('Foo\Zoo', '/root/packages/FooZoo');
 *
 *    // to load namespace-less class
 *        // adds the path to the class files
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
	 * Class name regexp.
	 *
	 * class name must:
	 *        - start with a CapitalLetter
	 *        - minimum length of 1
	 *        - only alphanumerics and _ are allowed
	 *
	 * @var string Class file name regular expression
	 */
	public const CLASS_FILE_REG = '~([A-Z][\w_]*)\.php$~';

	/**
	 * A file path, relative to a namespace's base directory and without `.php`, that names a class.
	 */
	private const PSR4_RELATIVE_REG = '~^[A-Za-z_]\w*(?:[/\\\][A-Za-z_]\w*)*$~';

	/**
	 * An associative array where the key is a namespace prefix and the value
	 * is an array of base directories for classes in that namespace.
	 *
	 * @var array
	 */
	protected static array $psr4_namespaces_map = [];

	/**
	 * Cache array of indexed dirs.
	 *
	 * @var array
	 */
	private static array $indexed_dirs = [];

	/**
	 * Array of classes name mapped to classes path.
	 *
	 * @var array
	 */
	private static array $class_map = [];

	/**
	 * Namespace prefixes whose base directory is only resolved when their first class is needed.
	 *
	 * @var array<string, callable():?string>
	 */
	private static array $lazy_namespaces = [];

	/**
	 * Classes mapped to their files by a production build ({@see useClassMap()}).
	 *
	 * @var array<string, string>
	 */
	private static array $compiled_map = [];

	/**
	 * The root namespaces (`OZONE\`) of the regular and lazy namespaces.
	 *
	 * @var array<string, true>
	 */
	private static array $roots = [];

	/**
	 * Registered as class loader or not.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Returns a report of the class loader.
	 *
	 * @return array
	 */
	public static function report(): array
	{
		return [
			'indexed_dirs'    => self::$indexed_dirs,
			'class_map'       => self::$class_map,
			'namespaces'      => self::$psr4_namespaces_map,
			'lazy_namespaces' => \array_keys(self::$lazy_namespaces),
		];
	}

	/**
	 * Adds directory to the indexed directories list.
	 *
	 * @param string $dir       the directory path
	 * @param bool   $recursive define if we should index sub directories
	 * @param int    $deep      define how deep we should go if recursive
	 */
	public static function addDir(string $dir, bool $recursive = false, int $deep = 1): void
	{
		// let's register our class loader if not done
		self::register();

		$dir = self::cleanPath($dir);

		if (!\is_dir($dir)) {
			throw new InvalidArgumentException(\sprintf('"%s" does not exists or is not a directory.', $dir));
		}

		if (\in_array($dir, self::$indexed_dirs, true)) {
			return;
		}

		self::$indexed_dirs[] = $dir;

		$res = \opendir($dir);

		if (!$res) {
			throw new RuntimeException(\sprintf('cannot open directory at: "%s"', $dir));
		}

		while (false !== ($filename = \readdir($res))) {
			if ('.' === $filename || '..' === $filename) {
				continue;
			}

			$c_path = $dir . \DIRECTORY_SEPARATOR . $filename;
			$in     = [];

			if (\is_file($c_path) && \preg_match(self::CLASS_FILE_REG, $filename, $in)) {
				$class_name = $in[1];

				if (!\array_key_exists($class_name, self::$class_map)) {
					self::$class_map[$class_name] = $c_path;
				}
			} elseif ($recursive && $deep > 0 && \is_dir($c_path)) {
				self::addDir($c_path, $recursive, $deep - 1);
			}
		}

		\closedir($res);
	}

	/**
	 * Adds directories to the indexed directories list.
	 *
	 * @param array $dirs      the directories list name
	 * @param bool  $recursive define if we should index sub directories
	 * @param int   $deep      define how deep we should go if recursive
	 */
	public static function addDirs(array $dirs, bool $recursive = false, int $deep = 1): void
	{
		foreach ($dirs as $dir) {
			if (\is_array($dir)) {
				$c_dir       = $dir[0] ?? null;
				$c_recursive = $dir[1] ?? $recursive;
				$c_deep      = $dir[2] ?? $deep;

				self::addDir($c_dir, $c_recursive, $c_deep);
			} else {
				self::addDir($dir, $recursive, $deep);
			}
		}
	}

	/**
	 * Adds a base directory for a namespace prefix.
	 *
	 * @param string $prefix   the namespace prefix
	 * @param string $base_dir a base directory for class files in the
	 *                         namespace
	 * @param bool   $prepend  if true, prepend the base directory to the stack
	 *                         instead of appending it; this causes it to be searched first rather
	 *                         than last
	 */
	public static function addNamespace(string $prefix, string $base_dir, bool $prepend = false): void
	{
		// let's register our class loader if not done
		self::register();

		$dir = self::cleanPath($base_dir);

		if (!\is_dir($dir)) {
			throw new InvalidArgumentException(\sprintf('"%s" does not exists or is not a directory.', $dir));
		}

		// normalize namespace prefix
		$prefix = \trim($prefix, '\\') . '\\';

		self::$roots[\strstr($prefix, '\\', true) . '\\'] = true;
		// normalize the base directory with a trailing separator
		$base_dir = \rtrim($base_dir, \DIRECTORY_SEPARATOR) . '/';
		// initialize the namespace prefix array
		if (false === isset(self::$psr4_namespaces_map[$prefix])) {
			self::$psr4_namespaces_map[$prefix] = [];
		}

		if (!\in_array($base_dir, self::$psr4_namespaces_map[$prefix], true)) {
			// retain the base directory for the namespace prefix
			if ($prepend) {
				\array_unshift(self::$psr4_namespaces_map[$prefix], $base_dir);
			} else {
				self::$psr4_namespaces_map[$prefix][] = $base_dir;
			}
		}
	}

	/**
	 * Adds a namespace whose base directory is only known once one of its classes is needed.
	 *
	 * The provider is called then, before the class loads (it may prepare what the classes need, such
	 * as a database for ORM classes), and the directory it returns is kept as a regular base
	 * directory of the namespace. A provider returning null is asked again for the next class.
	 *
	 * @param string             $prefix       the namespace prefix
	 * @param callable():?string $dir_provider returns the base directory, or null when not available yet
	 */
	public static function addLazyNamespace(string $prefix, callable $dir_provider): void
	{
		self::register();

		$prefix = \trim($prefix, '\\') . '\\';

		self::$lazy_namespaces[$prefix]                    = $dir_provider;
		self::$roots[\strstr($prefix, '\\', true) . '\\']  = true;
	}

	/**
	 * Maps classes to their files, as a production build lists them ({@see mapNamespaces()}): found
	 * without looking in the namespace's directories.
	 *
	 * @param array<string, string> $map class name -> file
	 */
	public static function useClassMap(array $map): void
	{
		self::register();

		self::$compiled_map = $map + self::$compiled_map;
	}

	/**
	 * Resolves every lazy namespace now ({@see addLazyNamespace()}): what a build that lists the
	 * classes needs. A namespace whose provider has no directory yet stays lazy.
	 */
	public static function resolveLazyNamespaces(): void
	{
		foreach (\array_keys(self::$lazy_namespaces) as $prefix) {
			if (isset(self::$lazy_namespaces[$prefix])) {
				self::resolveLazyNamespace($prefix);
			}
		}
	}

	/**
	 * The classes of every namespace's base directories, found by the PSR-4 rule from their file
	 * names: what a production class map lists. Lazy namespaces not resolved yet are not listed.
	 *
	 * @return array<string, string> class name -> file
	 */
	public static function mapNamespaces(): array
	{
		$map = [];

		foreach (self::$psr4_namespaces_map as $prefix => $dirs) {
			foreach ($dirs as $dir) {
				$root = \rtrim($dir, '/\\');

				if (!\is_dir($root)) {
					continue;
				}

				$files = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
				);

				foreach ($files as $file) {
					$path     = $file->getPathname();
					$relative = \substr($path, \strlen($root) + 1, -4);

					if (!\str_ends_with($path, '.php') || !\preg_match(self::PSR4_RELATIVE_REG, $relative)) {
						continue;
					}

					// the first base directory wins, as it does when searching
					$map[$prefix . \str_replace(['/', '\\'], '\\', $relative)] ??= $path;
				}
			}
		}

		return $map;
	}

	/**
	 * Loads the class file for a given class name fully-qualified or not.
	 *
	 * @param string $class_name the class name
	 *
	 * @return false|string the mapped file name on success, or false on failure
	 */
	public static function loadClass(string $class_name): bool|string
	{
		$file = self::findFile($class_name);

		if (null === $file) {
			return false;
		}

		require $file;

		return $file;
	}

	/**
	 * The file a class would be loaded from, without loading it: null when none is found.
	 *
	 * The directory of a lazy namespace is resolved on the way.
	 *
	 * @param string $class_name the class name, fully-qualified or not
	 */
	public static function findFile(string $class_name): ?string
	{
		if (isset(self::$compiled_map[$class_name])) {
			// A class of a lazy namespace still waits for its provider (the database for ORM classes).
			if (!empty(self::$lazy_namespaces)) {
				self::resolveLazyPrefixes($class_name);
			}

			return self::$compiled_map[$class_name];
		}

		if (false !== ($pos = \strpos($class_name, '\\'))) {
			// Registered ahead of Composer, this loader is asked for every class first: one whose root
			// namespace none of its namespaces has (Gobl, php-utils, a PSR interface) is Composer's.
			if (!isset(self::$roots[\substr($class_name, 0, $pos + 1)])) {
				return null;
			}

			return self::findPsr4File($class_name);
		}

		$path = self::$class_map[$class_name] ?? null;

		return null !== $path && \file_exists($path) ? $path : null;
	}

	/**
	 * Checks if the class exists.
	 *
	 * @param string $class_name The class name
	 *
	 * @return bool
	 */
	public static function exists(string $class_name): bool
	{
		if (!empty($class_name)) {
			self::loadClass($class_name);

			return \class_exists($class_name);
		}

		return false;
	}

	/**
	 * Register loader with SPL autoloader stack.
	 *
	 * Ahead of the others (Composer's): a class of a namespace registered here is found without their
	 * lookups, which for a namespace they also map (OZone's generated ORM classes, under `OZONE\Core\`)
	 * would first look for the file in a directory it is not in.
	 */
	protected static function register(): void
	{
		if (false === self::$registered) {
			self::$registered = true;
			\spl_autoload_register(static function ($class_name): void {
				self::loadClass($class_name);
			}, true, true);
		}
	}

	/**
	 * Loads the class file for a given fully-qualified class name.
	 *
	 * @param string $class the fully-qualified class name
	 *
	 * @return false|string the mapped file name on success, or false on failure
	 */
	protected static function loadClassPsr4(string $class): bool|string
	{
		$file = self::findPsr4File($class);

		if (null === $file) {
			return false;
		}

		require $file;

		return $file;
	}

	/**
	 * Load the mapped file for a namespace prefix and relative class.
	 *
	 * @param string $prefix         the namespace prefix
	 * @param string $relative_class the relative class name
	 *
	 * @return false|string boolean false if no mapped file can be loaded, or the
	 *                      name of the mapped file that was loaded
	 */
	protected static function getPsr4MappedFile(string $prefix, string $relative_class): false|string
	{
		// are there any base directories for this namespace prefix?
		if (false === isset(self::$psr4_namespaces_map[$prefix])) {
			return false;
		}
		// look through base directories for this namespace prefix
		foreach (self::$psr4_namespaces_map[$prefix] as $base_dir) {
			// replace the namespace prefix with the base directory,
			// replace namespace separators with directory separators
			// in the relative class name, append with .php
			$file = $base_dir . \str_replace('\\', '/', $relative_class) . '.php';
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
	 * Cleans a given directory path.
	 *
	 * @param string $dir_path the directory path
	 *
	 * @return string
	 */
	protected static function cleanPath(string $dir_path): string
	{
		if (!empty($dir_path)) {
			// removes last / or \
			$dir_path = \rtrim($dir_path, '\/');

			if (\DIRECTORY_SEPARATOR === '\\') {
				$dir_path = \str_replace('/', '\\', $dir_path);
			}
		}

		return $dir_path;
	}

	/**
	 * If a file exists, require it from the file system.
	 *
	 * @param string $file the file to require
	 *
	 * @return bool true if the file exists, false if not
	 */
	protected static function requireFile(string $file): bool
	{
		if (\file_exists($file)) {
			require $file;

			return true;
		}

		return false;
	}

	/**
	 * The file of a fully-qualified class, working backwards through its namespace prefixes: null when
	 * none is mapped.
	 */
	private static function findPsr4File(string $class): ?string
	{
		$prefix = $class;

		while (false !== $pos = \strrpos($prefix, '\\')) {
			// retain the trailing namespace separator in the prefix
			$prefix = \substr($class, 0, $pos + 1);

			if (isset(self::$lazy_namespaces[$prefix])) {
				self::resolveLazyNamespace($prefix);
			}

			if (isset(self::$psr4_namespaces_map[$prefix])) {
				$relative = \str_replace('\\', '/', \substr($class, $pos + 1)) . '.php';

				foreach (self::$psr4_namespaces_map[$prefix] as $base_dir) {
					if (\file_exists($base_dir . $relative)) {
						return $base_dir . $relative;
					}
				}
			}

			// removes the trailing namespace separator for the next iteration
			$prefix = \rtrim($prefix, '\\');
		}

		return null;
	}

	/**
	 * Resolves the lazy namespaces a class belongs to.
	 */
	private static function resolveLazyPrefixes(string $class): void
	{
		$prefix = $class;

		while (false !== $pos = \strrpos($prefix, '\\')) {
			$prefix = \substr($class, 0, $pos + 1);

			if (isset(self::$lazy_namespaces[$prefix])) {
				self::resolveLazyNamespace($prefix);
			}

			$prefix = \rtrim($prefix, '\\');
		}
	}

	/**
	 * Asks a lazy namespace's provider for its base directory, and keeps it once it has one.
	 *
	 * The provider may load classes of the same namespace (a database being initialized loads ORM
	 * classes): the directory is kept by whichever call gets it first.
	 */
	private static function resolveLazyNamespace(string $prefix): void
	{
		$dir = (self::$lazy_namespaces[$prefix])();

		if (null !== $dir && isset(self::$lazy_namespaces[$prefix])) {
			unset(self::$lazy_namespaces[$prefix]);

			self::addNamespace($prefix, $dir);
		}
	}
}
