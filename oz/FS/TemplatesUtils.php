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

namespace OZONE\OZ\FS;

use OTpl\OTpl;
use OZONE\OZ\Cache\CacheManager;
use OZONE\OZ\Exceptions\RuntimeException;
use PHPUtils\FS\PathUtils;
use PHPUtils\Str;
use Throwable;

/**
 * Class TemplatesUtils.
 */
class TemplatesUtils
{
	public const OZ_TEMPLATES_DIR = OZ_OZONE_DIR . 'oz_templates';

	public const APP_TEMPLATES_DIR = OZ_APP_DIR . 'oz_templates';

	/**
	 * ozone templates sources directories.
	 *
	 * @var array
	 */
	private static array $oz_sources_dir = [self::OZ_TEMPLATES_DIR];

	/**
	 * app templates sources directories.
	 *
	 * @var array
	 */
	private static array $app_sources_dir = [self::APP_TEMPLATES_DIR];

	/**
	 * adds templates sources directory.
	 *
	 * @param string $path templates files directory path
	 */
	public static function addSource(string $path): void
	{
		if (!\in_array($path, self::$oz_sources_dir, true) && !\in_array($path, self::$app_sources_dir, true)) {
			$fm   = new FilesManager();
			$path = $fm->resolve($path);

			$fm->filter()->isDir()->isReadable()->assert($path);

			if (\str_starts_with($path, OZ_OZONE_DIR)) {
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
	 * @return string the template result output
	 */
	public static function compile(string $template, array $data): string
	{
		$src = self::localize($template);

		if (!$src) {
			throw new RuntimeException(\sprintf('Unable to locate template file: %s', $template));
		}

		try {
			$o      = new OTpl();
			$result = $o->parse($src)
				->runGet($data);
		} catch (Throwable $t) {
			throw new RuntimeException(\sprintf('Unable to compile template file: %s', $template), null, $t);
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
	 * @return false|string the template file path, or false when template file does not exists
	 */
	public static function localize(string $template): false|string
	{
		$without_prefix = Str::removePrefix($template, 'oz://');

		if (!($without_prefix === $template)) {
			return PathUtils::resolve(self::OZ_TEMPLATES_DIR, $without_prefix);
		}

		$without_prefix = Str::removePrefix($template, 'app://');

		if (!($without_prefix === $template)) {
			return PathUtils::resolve(self::APP_TEMPLATES_DIR, $without_prefix);
		}

		if (!PathUtils::isRelative($template)) {
			return $template;
		}

		return CacheManager::runtime(self::class)->getFactory($template, function () use ($template) {
			$sources_group = [self::$app_sources_dir, self::$oz_sources_dir];
			$found         = false;

			foreach ($sources_group as $group) {
				// we start in the last added source
				for ($i = \count($group) - 1; $i >= 0; --$i) {
					$source = $group[$i];
					$path   = $source . DS . $template;

					if (\file_exists($path)) {
						$found = $path;

						break 2;
					}
				}
			}

			return $found;
		})->get();
	}
}
