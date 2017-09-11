<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\FS;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	class OZoneFS
	{

		/**
		 * current directory root.
		 *
		 * @var string
		 */
		private $root;

		/**
		 * OZoneFS constructor.
		 *
		 * @param string $root the directory root path
		 */
		public function __construct($root)
		{
			$this->root = OZonePath::resolve(OZ_APP_DIR, $root);
		}

		/**
		 * change the current directory root path.
		 *
		 * @param string $path        the path to set as root
		 * @param bool   $auto_create to automatically create directory
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function cd($path, $auto_create = false)
		{
			$abs_path = OZonePath::resolve($this->root, $path);

			if (file_exists($abs_path)) {
				if (!is_dir($abs_path)) {
					throw new \Exception(sprintf('"%s" is not a directory.', $abs_path));
				}
			} elseif ($auto_create === true) {
				$this->mkdir($abs_path);
			} else {
				throw new \Exception(sprintf('"%s" does not exists.', $abs_path));
			}

			$this->root = $abs_path;

			return $this;
		}

		/**
		 * create a symbolic link to a given target.
		 *
		 * @param string $target the target path
		 * @param string $name   the link name
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function ln($target, $name)
		{
			$abs_target = OZonePath::resolve($this->root, $target);
			$abs_dest   = OZonePath::resolve($this->root, $name);

			if (!file_exists($abs_target)) {
				throw new \Exception(sprintf('"%s" does not exists.', $abs_target));
			} elseif (file_exists($abs_dest)) {
				throw new \Exception(sprintf('cannot overwrite "%s".', $abs_dest));
			}

			symlink($abs_target, $abs_dest);

			return $this;
		}

		/**
		 * copy file/directory.
		 *
		 * @param string      $from the file/directory to copy
		 * @param string|null $to   the destination path
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function cp($from, $to = null)
		{
			$to = empty($to) ? $this->root : (string)$to;

			$abs_from = OZonePath::resolve($this->root, (string)$from);
			$abs_to   = OZonePath::resolve($this->root, $to);

			if (file_exists($abs_from)) {
				if (is_dir($abs_from)) {
					if (file_exists($abs_to) AND !is_dir($abs_to)) {
						throw new \Exception(sprintf('cannot overwrite non-directory "%s" with directory "%s".', $abs_to, $abs_from));
					}

					$this->mkdir($abs_to);
					self::recurseCopyDirAbsPath($abs_from, $abs_to);
				} else {
					if (is_dir($abs_to)) {
						$abs_to = OZonePath::resolve($abs_to, basename($abs_from));
					}

					$this->mkdir(dirname($abs_to));
					copy($abs_from, $abs_to);
				}
			} else {
				throw new \Exception(sprintf('"%s" does not exists.', $abs_from));
			}

			return $this;
		}

		/**
		 * write content to file.
		 *
		 * @param string $path    the file path
		 * @param string $content the content
		 * @param string $mode    php file write mode
		 *
		 * @return $this
		 */
		public function wf($path, $content = '', $mode = 'w')
		{
			$abs_path = OZonePath::resolve($this->root, $path);

			$f = fopen($abs_path, $mode);
			fwrite($f, $content);
			fclose($f);

			return $this;
		}

		/**
		 * create a directory at a given path with all parents directories
		 *
		 * @param string $path The directory path
		 * @param int    $mode The mode is 0777 by default, which means the widest possible access
		 *
		 * @return $this
		 * @throws \Exception
		 */
		public function mkdir($path, $mode = 0777)
		{
			$abs_path = OZonePath::resolve($this->root, $path);

			if (!is_dir($abs_path)) {
				if (false === @mkdir($abs_path, $mode, true) AND !is_dir($abs_path)) {
					throw new \Exception(sprintf('cannot create directory: "%s"', $abs_path));
				}
			}

			return $this;
		}

		/**
		 * remove file/directory at a given path.
		 *
		 * @param string $path the file/directory path
		 *
		 * @return $this
		 */
		public function rm($path)
		{
			$abs_path = OZonePath::resolve($this->root, $path);

			if (is_file($abs_path)) {
				unlink($abs_path);
			} else {
				self::recurseRemoveDirAbsPath($abs_path);
			}

			return $this;
		}

		/**
		 * remove directory recursively.
		 *
		 * @param string $path the directory path
		 *
		 * @return bool
		 */
		private static function recurseRemoveDirAbsPath($path)
		{
			$files = scandir($path);

			foreach ($files as $file) {
				if (!($file === '.' OR $file === '..')) {
					$src = $path . DS . $file;
					if (is_dir($src)) {
						self::recurseRemoveDirAbsPath($src);
					} else {
						unlink($src);
					}
				}
			}

			return rmdir($path);
		}

		/**
		 * copy directory recursively.
		 *
		 * @param string $src
		 * @param string $dest
		 */
		private static function recurseCopyDirAbsPath($src, $dest)
		{
			$res = opendir($src);
			@mkdir($dest);

			while (false !== ($file = readdir($res))) {
				if ($file != '.' AND $file != '..') {
					$from = $src . DS . $file;
					$to   = $dest . DS . $file;

					if (is_dir($from)) {
						self::recurseCopyDirAbsPath($from, $to);
					} else {
						copy($from, $to);
					}
				}
			}

			closedir($res);
		}

		/**
		 * check if a given directory is empty
		 *
		 * @param string $dir the directory path
		 *
		 * @return bool|null
		 */
		public static function isEmptyDir($dir)
		{
			if (!is_readable($dir)) return null;

			$handle = opendir($dir);
			$yes    = true;

			while (false !== ($entry = readdir($handle))) {
				if ($entry !== '.' && $entry !== '..') {
					$yes = false;
					break;
				}
			}

			closedir($handle);

			return $yes;
		}
	}