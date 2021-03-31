<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\FS;

class FilesManager
{
	/**
	 * Current directory root.
	 *
	 * @var string
	 */
	private $root;

	/**
	 * FilesManager constructor.
	 *
	 * @param string $root the directory root path
	 */
	public function __construct($root = '.')
	{
		$this->root = PathUtils::resolve(OZ_PROJECT_DIR, $root);
	}

	/**
	 * Gets current root.
	 *
	 * @return string
	 */
	public function getRoot()
	{
		return $this->root;
	}

	/**
	 * Resolve file to current root.
	 *
	 * @param string $target the path to resolve
	 *
	 * @return string
	 */
	public function resolve($target)
	{
		return PathUtils::resolve($this->root, $target);
	}

	/**
	 * Change the current directory root path.
	 *
	 * @param string $path        the path to set as root
	 * @param bool   $auto_create to automatically create directory
	 *
	 * @return $this
	 */
	public function cd($path, $auto_create = false)
	{
		$abs_path = PathUtils::resolve($this->root, $path);

		if ($auto_create === true && !\file_exists($abs_path)) {
			$this->mkdir($abs_path);
		}

		$this->assert($abs_path, ['directory' => true]);

		$this->root = $abs_path;

		return $this;
	}

	/**
	 * Creates a symbolic link to a given target.
	 *
	 * @param string $target the target path
	 * @param string $name   the link name
	 *
	 * @return $this
	 */
	public function ln($target, $name)
	{
		$abs_target      = PathUtils::resolve($this->root, $target);
		$abs_destination = PathUtils::resolve($this->root, $name);

		$this->assert($abs_target);
		$this->assertDoesNotExists($abs_destination);

		\symlink($abs_target, $abs_destination);

		return $this;
	}

	/**
	 * Copy file/directory.
	 *
	 * @param string      $from    the file/directory to copy
	 * @param null|string $to      the destination path
	 * @param null|array  $options the options
	 *
	 * @return $this
	 */
	public function cp($from, $to = null, array $options = [])
	{
		$to = empty($to) ? $this->root : (string) $to;

		$abs_from = PathUtils::resolve($this->root, (string) $from);
		$abs_to   = PathUtils::resolve($this->root, $to);

		$this->assert($abs_from);

		if (\is_dir($abs_from)) {
			if (\file_exists($abs_to)) {
				if (!\is_dir($abs_to)) {
					\trigger_error(\sprintf(
						'Cannot overwrite "%s" with "%s", the resource exists and is not a directory.',
						$abs_to,
						$abs_from
					), \E_USER_ERROR);
				}
			} else {
				$this->mkdir($abs_to);
			}

			$this->recursivelyCopyDirAbsPath($abs_from, $abs_to, $options);
		} else {
			if (\is_dir($abs_to)) {
				$abs_to = PathUtils::resolve($abs_to, \basename($abs_from));
			}

			$this->mkdir(\dirname($abs_to));
			\copy($abs_from, $abs_to);
		}

		return $this;
	}

	/**
	 * Copy a file from a url to a destination on the server.
	 *
	 * @param string      $url The file url to copy
	 * @param null|string $to  The destination path
	 *
	 * @return $this
	 */
	public function cpUrl($url, $to = null)
	{
		if (!\filter_var($url, \FILTER_VALIDATE_URL)) {
			\trigger_error(\sprintf('Invalid url: "%s".', $url), \E_USER_ERROR);
		}

		if (empty($to)) {
			$to = \basename($url);
		}

		$destination = PathUtils::resolve($this->root, $to);

		$this->assertDoesNotExists($destination);

		$file = \fopen($url, 'rb');

		if (!$file) {
			\trigger_error(\sprintf('Can\'t open file at: "%s".', $url), \E_USER_ERROR);
		} else {
			$fc = \fopen($destination, 'wb');

			while (!\feof($file)) {
				$line = \fread($file, 1028);
				\fwrite($fc, $line);
			}

			\fclose($fc);
		}

		return $this;
	}

	/**
	 * Walks in a given directory.
	 *
	 * When the current file is a directory, the walker callable
	 * should return false in order to prevent recursion.
	 *
	 * @param string   $dir_path directory path
	 * @param callable $walker   called for each file and directory
	 *
	 * @return $this
	 */
	public function walk($dir_path, callable $walker)
	{
		$abs_dir = PathUtils::resolve($this->root, (string) $dir_path);

		$this->assert($abs_dir, ['directory' => true, 'read' => true]);

		$res = \opendir($abs_dir);

		while (false !== ($file = \readdir($res))) {
			if ($file != '.' && $file != '..') {
				$path = PathUtils::resolve($abs_dir, $file);

				if (\is_dir($path)) {
					$ret = \call_user_func_array($walker, [$file, $path, true]);

					if ($ret !== false) {
						$this->walk($path, $walker);
					}
				} elseif (\is_file($path)) {
					\call_user_func_array($walker, [$file, $path, false]);
				}
			}
		}

		return $this;
	}

	/**
	 * Gets a given directory content info.
	 *
	 * @param string $path the directory path
	 *
	 * @return array
	 */
	public function info($path)
	{
		$abs_path = $this->resolve($path);

		$this->assert($abs_path, ['directory' => true]);

		$result = [];
		$files  = \scandir($abs_path);

		foreach ($files as $entry) {
			if ($entry != '.' && $entry !== '..') {
				$abs_entry = PathUtils::resolve($abs_path, $entry);

				$deletable = ((!\is_dir($abs_entry) && \is_writable($abs_path)) ||
							  (\is_dir($abs_entry) && \is_writable($abs_path) && $this->isRemovable($abs_entry)));
				$stat      = \stat($abs_entry);
				$result[]  = [
					'mtime'     => $stat['mtime'],
					'size'      => $stat['size'],
					'name'      => \basename($abs_entry),
					'delete'    => $deletable,
					'directory' => \is_dir($abs_entry),
					'read'      => \is_readable($abs_entry),
					'write'     => \is_writable($abs_entry),
					'execute'   => \is_executable($abs_entry),
				];
			}
		}

		return $result;
	}

	/**
	 * Get path info
	 *
	 * Thanks to: https://www.php.net/manual/en/function.stat.php#87241
	 *
	 * @param $path
	 *
	 * @return array|bool
	 */
	public function pathInfo($path)
	{
		$abs_path = $this->resolve($path);

		\clearstatcache();

		$ss = \stat($abs_path);

		if (!$ss) {
			return false;
		} // Couldn't stat file

		$ts = [
			0140000 => 'ssocket',
			0120000 => 'llink',
			0100000 => '-file',
			0060000 => 'bblock',
			0040000 => 'ddir',
			0020000 => 'cchar',
			0010000 => 'pfifo',
		];

		$p = $ss['mode'];
		$t = \decoct($p & 0170000); // File Encoding Bit

		$str = (\array_key_exists(\octdec($t), $ts)) ? $ts[\octdec($t)][0] : 'u';
		$str .= (($p & 0x0100) ? 'r' : '-') . (($p & 0x0080) ? 'w' : '-');
		$str .= (($p & 0x0040) ? (($p & 0x0800) ? 's' : 'x') : (($p & 0x0800) ? 'S' : '-'));
		$str .= (($p & 0x0020) ? 'r' : '-') . (($p & 0x0010) ? 'w' : '-');
		$str .= (($p & 0x0008) ? (($p & 0x0400) ? 's' : 'x') : (($p & 0x0400) ? 'S' : '-'));
		$str .= (($p & 0x0004) ? 'r' : '-') . (($p & 0x0002) ? 'w' : '-');
		$str .= (($p & 0x0001) ? (($p & 0x0200) ? 't' : 'x') : (($p & 0x0200) ? 'T' : '-'));

		$s = [
			'umask'         => \sprintf('%04o', \umask()),
			'human'         => $str,
			'octal1'        => \sprintf('%o', ($p & 000777)),
			'octal2'        => \sprintf('0%o', 0777 & $p),
			'decimal'       => \sprintf('%04o', $p),
			'fileperms'     => \fileperms($abs_path),
			'mode'          => $p,
			'filename'      => $abs_path,
			'realpath'      => (\realpath($abs_path) != $abs_path) ? \realpath($abs_path) : '',
			'dirname'       => \dirname($abs_path),
			'basename'      => \basename($abs_path),
			'type'          => \substr($ts[\octdec($t)], 1),
			'type_octal'    => \sprintf('%07o', \octdec($t)),
			'is_file'       => \is_file($abs_path),
			'is_dir'        => \is_dir($abs_path),
			'is_link'       => \is_link($abs_path),
			'is_readable'   => \is_readable($abs_path),
			'is_writable'   => \is_writable($abs_path),
			'is_executable' => \is_executable($abs_path),
			'size'          => $ss['size'], //Size of file, in bytes.
			'blocks'        => $ss['blocks'], //Number 512-byte blocks allocated
			'block_size'    => $ss['blksize'] //Optimal block size for I/O.
			,
			'mtime'         => $ss['mtime'], //Time of last modification
			'atime'         => $ss['atime'], //Time of last access
			'ctime'         => $ss['ctime'], //Time of last status change

		];

		$s['device'] = [
			'device'        => $ss['dev'], // Device
			'device_number' => $ss['rdev'], // Device number, if device.
			'inode'         => $ss['ino'], // File serial number
			'link_count'    => $ss['nlink'], // link count
			'link_to'       => ($ss['filetype']['type'] === 'link') ? \readlink($abs_path) : '',
		];

		\clearstatcache();

		return $s;
	}

	/**
	 * Get full path info
	 *
	 * Thanks to: https://www.php.net/manual/en/function.stat.php#87241
	 *
	 * @param $path
	 *
	 * @return array|bool
	 */
	public function fullPathInfo($path)
	{
		$abs_path = $this->resolve($path);

		\clearstatcache();

		$ss = \stat($abs_path);

		if (!$ss) {
			return false;
		} // Couldn't stat file

		$ts = [
			0140000 => 'ssocket',
			0120000 => 'llink',
			0100000 => '-file',
			0060000 => 'bblock',
			0040000 => 'ddir',
			0020000 => 'cchar',
			0010000 => 'pfifo',
		];

		$p = $ss['mode'];
		$t = \decoct($p & 0170000); // File Encoding Bit

		$str = (\array_key_exists(\octdec($t), $ts)) ? $ts[\octdec($t)][0] : 'u';
		$str .= (($p & 0x0100) ? 'r' : '-') . (($p & 0x0080) ? 'w' : '-');
		$str .= (($p & 0x0040) ? (($p & 0x0800) ? 's' : 'x') : (($p & 0x0800) ? 'S' : '-'));
		$str .= (($p & 0x0020) ? 'r' : '-') . (($p & 0x0010) ? 'w' : '-');
		$str .= (($p & 0x0008) ? (($p & 0x0400) ? 's' : 'x') : (($p & 0x0400) ? 'S' : '-'));
		$str .= (($p & 0x0004) ? 'r' : '-') . (($p & 0x0002) ? 'w' : '-');
		$str .= (($p & 0x0001) ? (($p & 0x0200) ? 't' : 'x') : (($p & 0x0200) ? 'T' : '-'));

		$s = [
			'perms' => [
				'umask'     => \sprintf('%04o', \umask()),
				'human'     => $str,
				'octal1'    => \sprintf('%o', ($p & 000777)),
				'octal2'    => \sprintf('0%o', 0777 & $p),
				'decimal'   => \sprintf('%04o', $p),
				'fileperms' => \fileperms($abs_path),
				'mode'      => $p,
			],

			'owner' => [
				'fileowner' => $ss['uid'],
				'filegroup' => $ss['gid'],
				'owner'     => (\function_exists('posix_getpwuid')) ?
					\posix_getpwuid($ss['uid']) : '',
				'group'     => (\function_exists('posix_getgrgid')) ?
					\posix_getgrgid($ss['gid']) : '',
			],

			'file' => [
				'filename' => $abs_path,
				'realpath' => (\realpath($abs_path) != $abs_path) ? \realpath($abs_path) : '',
				'dirname'  => \dirname($abs_path),
				'basename' => \basename($abs_path),
			],

			'filetype' => [
				'type'          => \substr($ts[\octdec($t)], 1),
				'type_octal'    => \sprintf('%07o', \octdec($t)),
				'is_file'       => \is_file($abs_path),
				'is_dir'        => \is_dir($abs_path),
				'is_link'       => \is_link($abs_path),
				'is_readable'   => \is_readable($abs_path),
				'is_writable'   => \is_writable($abs_path),
				'is_executable' => \is_executable($abs_path),
			],

			'size' => [
				'size'       => $ss['size'], //Size of file, in bytes.
				'blocks'     => $ss['blocks'], //Number 512-byte blocks allocated
				'block_size' => $ss['blksize'], //Optimal block size for I/O.
			],

			'time' => [
				'mtime' => $ss['mtime'], //Time of last modification
				'atime' => $ss['atime'], //Time of last access
				'ctime' => $ss['ctime'], //Time of last status change
			],
		];

		$s['device'] = [
			'device'        => $ss['dev'], // Device
			'device_number' => $ss['rdev'], // Device number, if device.
			'inode'         => $ss['ino'], // File serial number
			'link_count'    => $ss['nlink'], // link count
			'link_to'       => ($ss['filetype']['type'] === 'link') ? \readlink($abs_path) : '',
		];

		\clearstatcache();

		return $s;
	}

	/**
	 * Write content to file.
	 *
	 * @param string $path    the file path
	 * @param string $content the content
	 * @param string $mode    php file write mode
	 *
	 * @return $this
	 */
	public function wf($path, $content = '', $mode = 'w')
	{
		$abs_path = PathUtils::resolve($this->root, $path);

		$f = \fopen($abs_path, $mode);
		\fwrite($f, $content);
		\fclose($f);

		return $this;
	}

	/**
	 * Creates a directory at a given path with all parents directories
	 *
	 * @param string $path The directory path
	 * @param int    $mode The mode is 0775 by default,
	 *
	 * @return $this
	 */
	public function mkdir($path, $mode = 0775)
	{
		$abs_path = PathUtils::resolve($this->root, $path);

		if (\file_exists($abs_path) && !\is_dir($abs_path)) {
			\trigger_error(\sprintf(
				'Cannot overwrite "%s", the resource exists and is not a directory.',
				$abs_path
			), \E_USER_ERROR);
		}

		if (!\is_dir($abs_path)) {
			if (false === @\mkdir($abs_path, $mode, true) && !\is_dir($abs_path)) {
				\trigger_error(\sprintf('Cannot create directory "%s".', $abs_path), \E_USER_ERROR);
			}
		}

		return $this;
	}

	/**
	 * Removes file/directory at a given path.
	 *
	 * @param string $path the file/directory path
	 *
	 * @return $this
	 */
	public function rm($path)
	{
		$abs_path = PathUtils::resolve($this->root, $path);

		$this->assert($abs_path, ['read' => true, 'write' => true]);

		if (\is_file($abs_path)) {
			\unlink($abs_path);
		} else {
			$this->recursivelyRemoveDirAbsPath($abs_path);
		}

		return $this;
	}

	/**
	 * Checks if a path is removable.
	 *
	 * @param $path
	 *
	 * @return bool
	 */
	public function isRemovable($path)
	{
		$path = $this->resolve($path);

		if (\is_file($path)) {
			return \is_readable($path) && \is_writable($path);
		}

		$stack = [$path];

		while ($dir = \array_pop($stack)) {
			if (!\is_readable($dir) || !\is_writable($dir)) {
				return false;
			}

			$files = \array_diff(\scandir($dir), ['.', '..']);

			foreach ($files as $file) {
				if (\is_dir($file)) {
					$stack[] = PathUtils::resolve($dir, $file);
				}
			}
		}

		return true;
	}

	/**
	 * Checks if the current root is equal to a given path.
	 *
	 * @param string $path the path to check
	 *
	 * @return bool
	 */
	public function isSelf($path)
	{
		$path = $this->resolve($path);

		return $path === $this->root;
	}

	/**
	 * Checks if the current root is parent of a given path.
	 *
	 * @param string $path the path to check
	 *
	 * @return bool
	 */
	public function isParentOf($path)
	{
		$path = $this->resolve($path);

		if ($path === $this->root) {
			return false;
		}

		return 0 === \strpos($path, $this->root);
	}

	/**
	 * Asserts the path existence and checks for some rules.
	 *
	 * @param string $path  the resource path
	 * @param array  $rules the rules for required access
	 */
	public function assert($path, array $rules = null)
	{
		$abs_path = $this->resolve($path);

		if (!\file_exists($abs_path)) {
			\trigger_error(\sprintf('"%s" does not exists.', $abs_path), \E_USER_ERROR);
		}

		if (!empty($rules)) {
			$is_file       = isset($rules['file']) ? (bool) ($rules['file']) : false;
			$is_dir        = isset($rules['directory']) ? (bool) ($rules['directory']) : false;
			$is_readable   = isset($rules['read']) ? (bool) ($rules['read']) : false;
			$is_writable   = isset($rules['write']) ? (bool) ($rules['write']) : false;
			$is_executable = isset($rules['execute']) ? (bool) ($rules['execute']) : false;

			if ($is_file && !\is_file($abs_path)) {
				\trigger_error(\sprintf('"%s" is not a valid file.', $abs_path), \E_USER_ERROR);
			}

			if ($is_dir && !\is_dir($abs_path)) {
				\trigger_error(\sprintf('"%s" is not a valid directory.', $abs_path), \E_USER_ERROR);
			}

			if ($is_readable && !\is_readable($abs_path)) {
				\trigger_error(\sprintf('The resource at "%s" is not readable.', $abs_path), \E_USER_ERROR);
			}

			if ($is_writable && !\is_writable($abs_path)) {
				\trigger_error(\sprintf('The resource at "%s" is not writeable.', $abs_path), \E_USER_ERROR);
			}

			if ($is_executable && !\is_executable($abs_path)) {
				\trigger_error(\sprintf('The resource at "%s" is not executable.', $abs_path), \E_USER_ERROR);
			}
		}
	}

	/**
	 * Asserts the path does not exists.
	 *
	 * @param string $abs_path the resource absolute path
	 */
	public function assertDoesNotExists($abs_path)
	{
		if (\file_exists($abs_path)) {
			\trigger_error(\sprintf('Cannot overwrite existing resource: %s', $abs_path), \E_USER_ERROR);
		}
	}

	/**
	 * Removes directory recursively.
	 *
	 * @param string $path the directory path
	 *
	 * @return bool
	 */
	private function recursivelyRemoveDirAbsPath($path)
	{
		$files = \scandir($path);

		foreach ($files as $file) {
			if (!($file === '.' || $file === '..')) {
				$src = $path . DS . $file;

				if (\is_dir($src)) {
					$this->recursivelyRemoveDirAbsPath($src);
				} else {
					\unlink($src);
				}
			}
		}

		return \rmdir($path);
	}

	/**
	 * Copy directory recursively.
	 *
	 * @param string $source
	 * @param string $destination
	 * @param array  $options
	 */
	private function recursivelyCopyDirAbsPath($source, $destination, array $options)
	{
		if (!\file_exists($destination)) {
			\mkdir($destination);
		}

		$exclude       = isset($options['exclude']) ? $options['exclude'] : null;
		$include       = isset($options['include']) ? $options['include'] : null;
		$check_exclude = \is_callable($exclude);
		$check_include = \is_callable($include);
		$b_name        = \basename($source);

		if ($check_exclude && \call_user_func($exclude, $b_name, $source, $destination . DS . $source)) {
			return;
		}

		if ($check_include && !\call_user_func($include, $b_name, $source, $destination . DS . $source)) {
			return;
		}

		$res = \opendir($source);

		while (false !== ($file = \readdir($res))) {
			if ($file != '.' && $file != '..') {
				$from = $source . DS . $file;
				$to   = $destination . DS . $file;

				if ($check_exclude && \call_user_func($exclude, $file, $from, $to)) {
					continue;
				}

				if ($check_include && !\call_user_func($include, $file, $from, $to)) {
					continue;
				}

				if (\is_dir($from)) {
					$this->recursivelyCopyDirAbsPath($from, $to, $options);
				} else {
					if (\file_exists($to) && !\is_file($to)) {
						\trigger_error(\sprintf(
							'Cannot overwrite "%s" the resource exists and is not a file.',
							$to
						), \E_USER_ERROR);
					}

					\copy($from, $to);
				}
			}
		}

		\closedir($res);
	}

	/**
	 * Checks if a given directory is empty
	 *
	 * @param string $dir the directory path
	 *
	 * @return null|bool
	 */
	public static function isEmptyDir($dir)
	{
		if (!\is_readable($dir)) {
			return null;
		}

		$handle = \opendir($dir);
		$yes    = true;

		while (false !== ($entry = \readdir($handle))) {
			if ($entry !== '.' && $entry !== '..') {
				$yes = false;

				break;
			}
		}

		\closedir($handle);

		return $yes;
	}
}
