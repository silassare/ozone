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

use Exception;
use OTpl\OTpl;
use OZONE\OZ\Exceptions\InternalErrorException;
use OZONE\OZ\Utils\StringUtils;

class TemplatesUtils
{
	const OZ_TEMPLATES_DIR = OZ_OZONE_DIR . 'oz_templates';

	const APP_TEMPLATES_DIR = OZ_APP_DIR . 'oz_templates';

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
	private static $oz_sources_dir = [self::OZ_TEMPLATES_DIR];

	/**
	 * app templates sources directories
	 *
	 * @var array
	 */
	private static $app_sources_dir = [self::APP_TEMPLATES_DIR];

	/**
	 * adds templates sources directory.
	 *
	 * @param string $path templates files directory path
	 */
	public static function addSource($path)
	{
		if (!\in_array($path, self::$oz_sources_dir) && !\in_array($path, self::$app_sources_dir)) {
			if (!\is_dir($path)) {
				\trigger_error(\sprintf('Invalid directory: %s', $path), \E_USER_ERROR);
			}

			if (0 === \strpos($path, OZ_OZONE_DIR)) {
				self::$oz_sources_dir[] = $path;
			} else {
				self::$app_sources_dir[] = $path;
			}
		}
	}

	/**
	 * compute a template file with a given data.
	 *
	 * @param string $template template file to compute
	 * @param array  $data     data to inject in template
	 *
	 * @throws \OZONE\OZ\Exceptions\BaseException
	 *
	 * @return string the template result output
	 */
	public static function compute($template, array $data)
	{
		$src = self::localize($template);

		if (!$src) {
			throw new InternalErrorException('OZ_TEMPLATE_FILE_NOT_FOUND', [
				'template' => $template,
			]);
		}

		try {
			$o      = new OTpl();
			$result = $o->parse($src)
						->runGet($data);
		} catch (Exception $e) {
			throw new InternalErrorException(null, null, $e);
		}

		return $result;
	}

	/**
	 * returns template according to available sources.
	 *
	 * when you use `oz://foo.tpl` it refer to template `foo.otpl`
	 * in ozone templates directory
	 *
	 * @param string $template the template file name
	 *
	 * @return bool|string the template file path, or false when template file does not exists
	 */
	public static function localize($template)
	{
		$without_prefix = StringUtils::removePrefix($template, 'oz://');

		if (!($without_prefix === $template)) {
			return PathUtils::resolve(self::OZ_TEMPLATES_DIR, $without_prefix);
		}

		$without_prefix = StringUtils::removePrefix($template, 'app://');

		if (!($without_prefix === $template)) {
			return PathUtils::resolve(self::APP_TEMPLATES_DIR, $without_prefix);
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
			// we start in the last added source
			for ($i = \count($group) - 1; $i >= 0; $i--) {
				$source = $group[$i];
				$path   = $source . DS . $template;

				if (\file_exists($path)) {
					self::$found_cache[$template] = $path;

					break 2;
				}
			}
		}

		if (isset(self::$found_cache[$template])) {
			return self::$found_cache[$template];
		}

		return false;
	}
}
