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

	use OZONE\OZ\Exceptions\InternalErrorException;
	use OZONE\OZ\Utils\StringUtils;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class TemplatesUtils
	{

		/**
		 * templates path cache.
		 *
		 * @var array
		 */
		private static $found_cache = [];

		/**
		 * ozone templates sources directories
		 *
		 * @var array
		 */
		private static $oz_sources_dir = [OZ_OZONE_DIR . 'oz_templates'];

		/**
		 * app templates sources directories
		 *
		 * @var array
		 */
		private static $app_sources_dir = [OZ_APP_DIR . 'oz_templates'];

		/**
		 * adds templates sources directory.
		 *
		 * @param string $path templates files directory path.
		 *
		 * @throws \Exception
		 */
		public static function addSource($path)
		{
			if (!in_array($path, self::$oz_sources_dir) AND !in_array($path, self::$app_sources_dir)) {
				if (!is_dir($path)) {
					throw new \Exception(sprintf('"%s" is not a directory.', $path));
				}

				if (0 === strpos($path, OZ_OZONE_DIR)) {
					self::$oz_sources_dir[] = $path;
				} else {
					self::$app_sources_dir[] = $path;
				}
			}
		}

		/**
		 * compute a template file with a given data.
		 *
		 * @param string $template template file to compute.
		 * @param array  $data     data to inject in template.
		 *
		 * @return string            the template result output.
		 */
		public static function compute($template, array $data)
		{
			$template = self::localize($template);

			$o = new \OTpl();

			return $o->parse($template)
					 ->runGet($data);
		}

		/**
		 * returns template according to available sources.
		 *
		 * when you use `oz:foo.tpl` it refer to template `foo.otpl`
		 * in ozone templates directory
		 *
		 * @param string $template the template file name.
		 *
		 * @return string            the template file path.
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException    when template file does not exists.
		 */
		public static function localize($template)
		{
			$no_prefix = StringUtils::removePrefix($template, 'oz:');

			if (!($no_prefix === $template)) {
				return PathUtils::resolve(OZ_OZONE_DIR . 'oz_templates', $no_prefix);
			}

			if (!PathUtils::isRelative($template)) {
				return $template;
			}

			if (isset(self::$found_cache[$template])) {
				return self::$found_cache[$template];
			}

			$sources_group = [self::$app_sources_dir, self::$oz_sources_dir];
			$found         = null;

			foreach ($sources_group as $group) {
				// we start search in the last added source
				for ($i = count($group) - 1; $i >= 0; $i--) {
					$source = $group[$i];
					$path   = $source . DS . $template;

					if (file_exists($path)) {
						self::$found_cache[$template] = $path;
						break 2;
					}
				}
			}

			if (isset(self::$found_cache[$template])) {
				return self::$found_cache[$template];
			}

			throw new InternalErrorException('OZ_TEMPLATE_FILE_NOT_FOUND', [$template]);
		}
	}
