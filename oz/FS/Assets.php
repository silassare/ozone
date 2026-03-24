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

use Override;
use OZONE\Core\Cache\CacheManager;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use PHPUtils\FS\PathUtils;
use PHPUtils\Str;

/**
 * Class Assets.
 *
 * This class manages asset files in OZone.
 * It allows registering multiple sources for assets and provides
 * a mechanism to localize asset paths using the "oz://" protocol.
 *
 * - `oz://~core~/foo.bar` refer to asset `foo.bar` in OZone core registered sources.
 * - `oz://~project~/foo.bar` refer to asset `foo.bar` in project registered sources.
 * - `oz://foo.bar` refer to asset `foo.bar` in all available registered sources.
 */
final class Assets implements BootHookReceiverInterface
{
	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function boot(): void
	{
		self::register();
	}

	/**
	 * This is separated from the boot hook because we need
	 * to register the resolver in cli context without app (when running cli out of an existing project).
	 *
	 * @internal
	 */
	public static function register(): void
	{
		// This allows us to use PathUtils::resolve('oz://path/to/asset') to get the localized path.
		// Typically useful in blate templates or other dependencies using PathUtils::resolve() method.
		PathUtils::registerResolver('oz', self::localize(...));
	}

	/**
	 * Returns the registered asset sources.
	 *
	 * @return PathSources
	 */
	public static function getSources(): PathSources
	{
		/** @var null|PathSources $sources */
		static $sources = null;

		if (null === $sources) {
			$sources = new PathSources();
			$sources->add(OZ_OZONE_DIR . 'oz_assets')
				->add(Templates::OZ_TEMPLATE_DIR);
		}

		return $sources;
	}

	/**
	 * Adds a new source directory for assets.
	 *
	 * @param string $path the path to the directory to add as a source
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
	 * Localizes a given path using the registered sources and caching mechanism.
	 *
	 * @param string $path the path to localize
	 *
	 * @return false|string the localized path or false if not found
	 */
	public static function localize(string $path): false|string
	{
		$oz_core_only_prefix = 'oz://~core~/';
		$project_only_prefix = 'oz://~project~/';
		$cache_key           = $path;
		$sources             = self::getSources();

		if (\str_starts_with($path, $oz_core_only_prefix)) {
			$sources_group = [$sources->getInternalSources()];
			$path          = Str::removePrefix($path, $oz_core_only_prefix);
		} elseif (\str_starts_with($path, $project_only_prefix)) {
			$sources_group = [$sources->getProjectSources()];
			$path          = Str::removePrefix($path, $project_only_prefix);
		} else {
			$path          = Str::removePrefix($path, 'oz://');
			$sources_group = [
				$sources->getProjectSources(),
				$sources->getPluginsSources(),
				$sources->getInternalSources(),
			];
		}

		if (!PathUtils::isRelative($path)) {
			return $path;
		}

		$factory = static function () use ($path, $sources_group): string|false {
			$found = false;

			foreach ($sources_group as $group) {
				// we start from the last added source
				for ($i = \count($group) - 1; $i >= 0; --$i) {
					$source     = $group[$i];
					$abs_path   = $source . DS . $path;

					if (\file_exists($abs_path)) {
						$found = $abs_path;

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
