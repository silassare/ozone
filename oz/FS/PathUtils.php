<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\FS;

	/**
	 * Class PathUtils
	 *
	 * original sources: https://gist.github.com/silassare/e048711c92ca9de0eca77e8e6d08a004
	 *
	 * @package OZONE\OZ\FS
	 */
	class PathUtils
	{
		const DS = DIRECTORY_SEPARATOR;

		/**
		 * resolve a given path according to a given root
		 *
		 * @param string $root the root path
		 * @param string $path the path to resolve
		 *
		 * @return string    the absolute path
		 */
		public static function resolve($root, $path)
		{
			$root = self::normalize($root);
			$path = self::normalize($path);

			if (self::isRelative($path)) {
				if ((self::DS === '/' AND $path[0] === '/') OR preg_match("#^[\w]+:#", $path)) {
					// path start form the root
					// linux - unix	-> /
					// windows		-> D:

					$full_path = $path;
				} else {
					$full_path = $root . self::DS . $path;
				}

				$path = preg_replace('#^(https?):[/]([^/])#', '$1://$2', self::job($full_path));
			}

			return $path;
		}

		/**
		 * where the resolve job is done
		 *
		 * @param string $path the path to normalize
		 *
		 * @return string the resolved path
		 */
		private static function job($path)
		{
			$in  = explode(self::DS, $path);
			$out = [];

			// preserve linux root first char '/' like in: /root/path/to/
			if ($path[0] === self::DS) {
				array_push($out, '');
			}

			foreach ($in as $part) {
				// tmp part that have no value
				if (empty($part) OR $part === '.') continue;

				if ($part !== '..') {
					// cool we found a new part
					array_push($out, $part);
				} elseif (count($out) > 0) {
					// going back up? sure
					array_pop($out);
				} else {
					// now here we don't like
					throw new \InvalidArgumentException(sprintf("Climbing above root is dangerous: %s", $path));
				}
			}

			if (!count($out)) {
				return self::DS;
			}

			if (count($out) === 1) {
				array_push($out, null);
			}

			return join(self::DS, $out);
		}

		/**
		 * normalize a given path according to OS specific directory separator
		 *
		 * @param string $path the path to normalize
		 *
		 * @return string    the normalized path
		 */
		public static function normalize($path)
		{
			if (self::DS == '\\') return strtr($path, '/', '\\');

			return strtr($path, '\\', '/');
		}

		/**
		 * Checks if a given path is relative or not
		 *
		 * @param string $path the path
		 *
		 * @return bool    true if it is a relative path, false otherwise
		 */
		public static function isRelative($path)
		{
			return preg_match("#^\.{1,2}[/\\\\]?#", $path) OR preg_match("#[/\\\\]\.{1,2}[/\\\\]#", $path) OR preg_match("#[/\\\\]\.{1,2}$#", $path) OR preg_match("#^[a-zA-Z0-9_.][^:]*$#", $path);
		}
	}