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

namespace OZONE\Core\FS;

use OTpl\OTpl;
use OZONE\Core\Cache\CacheManager;
use OZONE\Core\Exceptions\RuntimeException;
use PHPUtils\FS\PathUtils;
use PHPUtils\Str;
use Throwable;

/**
 * Class Templates.
 */
class Templates
{
	/**
	 * ozone templates directory.
	 *
	 * @var string
	 */
	public const OZ_TEMPLATE_DIR = OZ_OZONE_DIR . 'oz_templates' . DS;

	/**
	 * ozone templates sources directories.
	 *
	 * @var array
	 */
	private static array $oz_sources_dir = [self::OZ_TEMPLATE_DIR];

	/**
	 * app templates sources directories.
	 *
	 * @var array
	 */
	private static array $app_sources_dir = [];

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

			$fm->filter()
				->isDir()
				->isReadable()
				->assert($path);

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
		$oz_only_prefix  = 'oz://';
		$app_only_prefix = 'app://';
		$cache_key       = $template;

		if (\str_starts_with($template, $oz_only_prefix)) {
			$sources_group = [self::$oz_sources_dir];
			$template      = Str::removePrefix($template, $oz_only_prefix);
		} elseif (\str_starts_with($template, $app_only_prefix)) {
			$sources_group = [self::$app_sources_dir];
			$template      = Str::removePrefix($template, $app_only_prefix);
		} else {
			$sources_group = [self::$app_sources_dir, self::$oz_sources_dir];
		}

		if (!PathUtils::isRelative($template)) {
			return $template;
		}

		$factory = static function () use ($template, $sources_group) {
			$found = false;

			foreach ($sources_group as $group) {
				// we start from the last added source
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
		};

		$cm = CacheManager::runtime(self::class);

		return $cm->factory($cache_key, $factory)
			->get();
	}
}
