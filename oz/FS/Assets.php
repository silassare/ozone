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
use OZONE\Core\App\Interfaces\AppInterface;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Scopes\Interfaces\ScopeInterface;
use OZONE\Core\Stores\CacheRegistry;
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
	 * The asset sources of the scope in use: {@see self::getSources()}.
	 */
	private static ?PathSources $sources = null;

	/**
	 * The runtime cache {@see self::localize()} keeps what it found in: one per set of sources, since
	 * the same path names another file in another scope.
	 */
	private static string $cache = self::class;

	/**
	 * The sources and cache of each scope this process has switched from or to, while another
	 * scope's are in use ({@see self::enterScope()}).
	 *
	 * @var array<string, array{sources: PathSources, cache: string}>
	 */
	private static array $layers = [];

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
	 */
	public static function getSources(): PathSources
	{
		if (null === self::$sources) {
			self::$sources = new PathSources();
			self::$sources->add(OZ_OZONE_DIR . 'oz_assets')
				->add(Templates::OZ_TEMPLATE_DIR);
		}

		return self::$sources;
	}

	/**
	 * Puts the asset sources of another scope in place of those in use, until
	 * {@see self::leaveScope()}: the same sources with that scope's templates directory in place of
	 * the left scope's, as its own entry point would have added it.
	 *
	 * @internal for {@see \OZONE\Core\OZone::inScope()}
	 *
	 * @param ScopeInterface $from the scope whose sources are in use
	 * @param ScopeInterface $to   the scope whose sources to use
	 */
	public static function enterScope(ScopeInterface $from, ScopeInterface $to): void
	{
		$current = ['sources' => self::getSources(), 'cache' => self::$cache];
		$layer   = self::$layers[$to->getName()] ?? null;

		if (null === $layer) {
			$sources = $current['sources']->without(...self::scopeTemplates($from));

			foreach (self::scopeTemplates($to) as $dir) {
				$sources->add($dir);
			}

			$layer = ['sources' => $sources, 'cache' => self::class . '@' . $to->getName()];
		}

		self::$layers[$from->getName()] = $current;
		self::$sources                  = $layer['sources'];
		self::$cache                    = $layer['cache'];
	}

	/**
	 * Puts back the asset sources of the scope {@see self::enterScope()} left.
	 *
	 * @internal for {@see \OZONE\Core\OZone::inScope()}
	 *
	 * @param ScopeInterface $to   the scope whose sources are in use
	 * @param ScopeInterface $back the scope whose sources to use again
	 */
	public static function leaveScope(ScopeInterface $to, ScopeInterface $back): void
	{
		$layer = self::$layers[$back->getName()] ?? null;

		if (null === $layer) {
			throw new RuntimeException(\sprintf('No asset sources were kept for the scope "%s".', $back->getName()));
		}

		self::$layers[$to->getName()] = ['sources' => self::getSources(), 'cache' => self::$cache];
		self::$sources                = $layer['sources'];
		self::$cache                  = $layer['cache'];
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

		$factory = static function () use ($path, $sources_group): false|string {
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

		return CacheRegistry::runtime(self::$cache)->remember($cache_key, $factory);
	}

	/**
	 * The templates directory a scope adds of its own, when it has one: none for the application,
	 * whose directory is every scope's.
	 *
	 * @return list<string>
	 */
	private static function scopeTemplates(ScopeInterface $scope): array
	{
		if ($scope instanceof AppInterface) {
			return [];
		}

		$dir = $scope->getSourcesDir()->getRoot() . DS . 'templates';

		return \is_dir($dir) ? [$dir] : [];
	}
}
