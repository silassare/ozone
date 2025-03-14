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

use Blate\Blate;
use OTpl\OTpl;
use OZONE\Core\App\Settings;
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
	 * Gets templates path sources.
	 *
	 * @return PathSources
	 */
	public static function getSources(): PathSources
	{
		/** @var null|PathSources $sources */
		static $sources = null;

		if (null === $sources) {
			$sources = new PathSources();
			$sources->add(self::OZ_TEMPLATE_DIR);
		}

		return $sources;
	}

	/**
	 * adds templates sources directory.
	 *
	 * @param string $path templates files directory path
	 */
	public static function addSource(string $path): void
	{
		$fm   = FS::fromRoot();
		$path = $fm->resolve($path);

		$fm->filter()
			->isDir()
			->isReadable()
			->assert($path);

		self::getSources()
			->add($path);
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
			if (\str_ends_with($src, '.blate')) {
				self::registerBlatePlugins();

				$b      = Blate::fromPath($src);
				$result = $b->runGet($data);
			} else {
				$o      = new OTpl();
				$result = $o->parse($src)
					->runGet($data);
			}
		} catch (Throwable $t) {
			throw new RuntimeException(\sprintf('Unable to compile template file: %s', $template), null, $t);
		}

		return $result;
	}

	/**
	 * returns template according to available sources.
	 *
	 * - `oz://~core~/foo.bar` refer to template `foo.bar` in core templates directory.
	 * - `oz://~project~/foo.bar` refer to template `foo.bar` in project templates directory.
	 * - `oz://foo.bar` refer to template `foo.bar` in all available templates directories.
	 *
	 * @param string $template the template file name
	 *
	 * @return false|string the template file path, or false when template file does not exists
	 */
	public static function localize(string $template): false|string
	{
		$oz_core_only_prefix = 'oz://~core~/';
		$project_only_prefix = 'oz://~project~/';
		$cache_key           = $template;
		$sources             = self::getSources();

		if (\str_starts_with($template, $oz_core_only_prefix)) {
			$sources_group = [$sources->getInternalSources()];
			$template      = Str::removePrefix($template, $oz_core_only_prefix);
		} elseif (\str_starts_with($template, $project_only_prefix)) {
			$sources_group = [$sources->getProjectSources()];
			$template      = Str::removePrefix($template, $project_only_prefix);
		} else {
			$template      = Str::removePrefix($template, 'oz://');
			$sources_group = [
				$sources->getProjectSources(),
				$sources->getPluginsSources(),
				$sources->getInternalSources(),
			];
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

	/**
	 * Register blate plugins.
	 */
	protected static function registerBlatePlugins(): void
	{
		static $registered = false;

		if (!$registered) {
			Blate::registerHelper('setting', [Settings::class, 'get']);
			Blate::registerHelper('env', env(...));
			Blate::registerHelper('log', oz_logger(...));

			$registered = true;
		}
	}
}
