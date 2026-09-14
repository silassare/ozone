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

namespace OZONE\Core\Cli\Build;

use JsonException;
use OZONE\Core\Cli\Deploy\ReleaseLayout;
use OZONE\Core\Cli\Process;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\Loader\ClassLoader;
use OZONE\Core\OZone;

/**
 * Prepares a project for production (`oz project build`).
 *
 * Compiles ahead what requests would otherwise compile at first use -- the compiled `.env`, the
 * settings bundles and the route tables of each scope ({@see ScopeBuilder}) -- then the class map
 * and, for a server that bootstraps for each request, the preload list.
 *
 * All of it is named after the release (the project directory): `.ozone/` is shared by the releases
 * of an `oz deploy` root, and a release must never read what another compiled.
 *
 * @internal
 */
final class ProjectBuilder
{
	/**
	 * The scopes of the project: its `scopes/{name}` directories.
	 *
	 * @return list<string>
	 */
	public static function scopes(): array
	{
		$scopes = [];

		foreach (\glob(OZ_PROJECT_DIR . 'scopes' . DS . '*', \GLOB_ONLYDIR) ?: [] as $dir) {
			$scopes[] = \basename($dir);
		}

		\sort($scopes);

		return $scopes;
	}

	/**
	 * Builds a scope, in a process of its own.
	 *
	 * @return array{scope: string, production: bool, settings_bundles: int, preload: list<string>}
	 *
	 * @throws JsonException
	 */
	public static function buildScope(string $scope): array
	{
		$process = new Process(
			[\PHP_BINARY, __DIR__ . DS . 'scope_build.php', $scope],
			OZ_PROJECT_DIR,
			null,
			null,
			300
		);

		$process->run();

		$lines = \preg_split('~\R~', \trim($process->getOutput())) ?: [];
		$last  = (string) \end($lines);

		if (!$process->isSuccessful() || !\str_starts_with($last, '{')) {
			throw new RuntimeException(\sprintf(
				'Building the scope "%s" failed: %s',
				$scope,
				\trim($process->getErrorOutput() . \PHP_EOL . $process->getOutput())
			));
		}

		return \json_decode($last, true, 512, \JSON_THROW_ON_ERROR);
	}

	/**
	 * Writes the class map of this release ({@see OZone::classMapFile()}): the classes of the
	 * namespaces OZone's class loader knows, the generated ORM classes included.
	 *
	 * @return int the classes mapped
	 */
	public static function writeClassMap(): int
	{
		ClassLoader::resolveLazyNamespaces();

		$map = ClassLoader::mapNamespaces();

		self::write(OZone::classMapFile(), 'return ' . \var_export($map, true) . ';');

		return \count($map);
	}

	/**
	 * How many classes were added, moved or removed since the class map of this release was written,
	 * or null when it has none.
	 */
	public static function classMapDrift(): ?int
	{
		$file = OZone::classMapFile();

		if (!\is_file($file)) {
			return null;
		}

		$built = (array) include $file;

		ClassLoader::resolveLazyNamespaces();

		$now = ClassLoader::mapNamespaces();

		return \count(\array_diff_assoc($now, $built)) + \count(\array_diff_key($built, $now));
	}

	/**
	 * Writes the preload list of this release -- the class files given, parents first -- and the
	 * script `opcache.preload` points to ({@see preloadStub()}).
	 *
	 * @param list<string> $files
	 */
	public static function writePreload(array $files): void
	{
		self::write(self::preloadListFor(self::root()), 'return ' . \var_export($files, true) . ';');
		self::write(self::preloadFile(), self::preloadStub());
	}

	/**
	 * The script `opcache.preload` points to: the same for every release.
	 */
	public static function preloadFile(): string
	{
		return self::root() . DS . '.ozone' . DS . 'preload.php';
	}

	/**
	 * The file listing what a release preloads.
	 *
	 * @param string $root the release: the real path of its project directory
	 */
	public static function preloadListFor(string $root): string
	{
		return $root . DS . '.ozone' . DS . 'cache' . DS . 'preload.' . \hash('xxh128', $root) . '.php';
	}

	/**
	 * How the preload list of this release compares with the files it lists: how many it lists, and
	 * how many of them are gone or changed since it was written. Null when it has none.
	 *
	 * @return null|array{files: int, missing: int, changed: int}
	 */
	public static function preloadDrift(): ?array
	{
		$list = self::preloadListFor(self::root());

		if (!\is_file($list)) {
			return null;
		}

		$built   = (int) \filemtime($list);
		$files   = 0;
		$missing = 0;
		$changed = 0;

		foreach ((array) include $list as $file) {
			if (!\is_string($file)) {
				continue;
			}

			++$files;

			if (!\is_file($file)) {
				++$missing;
			} elseif (\filemtime($file) > $built) {
				++$changed;
			}
		}

		return ['files' => $files, 'missing' => $missing, 'changed' => $changed];
	}

	/**
	 * The code of the script `opcache.preload` points to.
	 *
	 * `.ozone/` is shared by the releases of an `oz deploy` root, so it holds a preload list per
	 * release, and the script picks one when PHP starts: that of the release `current` points to,
	 * or of the project itself outside a deploy root. A release that failed before going live is
	 * never preloaded, and a rollback preloads the release it went back to once PHP restarts. Files
	 * gone since (a pruned release) are skipped.
	 */
	public static function preloadStub(): string
	{
		return \sprintf(
			<<<'PHP'
				// Generated by `oz project build`: what opcache.preload points to. Compiles the class files a
				// request of the live release loads, once, when PHP starts: preloaded code only changes when PHP
				// restarts, so restart it after a build.

				$base = \dirname(__DIR__);
				$live = \is_link($base . '/%s') && \is_dir($base . '/%s') ? \realpath($base . '/%s') : false;
				$root = false === $live ? $base : $live;
				$list = __DIR__ . '/cache/preload.' . \hash('xxh128', $root) . '.php';

				if (\is_file($list)) {
					foreach ((array) require $list as $file) {
						if (\is_string($file) && \is_file($file)) {
							\opcache_compile_file($file);
						}
					}
				}
				PHP,
			ReleaseLayout::CURRENT,
			ReleaseLayout::RELEASES,
			ReleaseLayout::CURRENT
		);
	}

	/**
	 * Removes what builds and requests compiled: the next ones compile it again.
	 *
	 * The preload script stays, since PHP refuses to start when `opcache.preload` points to nothing,
	 * and so do the preload lists of other releases: none of them is compiled again by a request.
	 *
	 * @return int the files removed
	 */
	public static function clear(): int
	{
		$cache = self::root() . DS . '.ozone' . DS . 'cache' . DS;
		$files = [
			...\glob($cache . 'env' . DS . '*.php') ?: [],
			...\glob($cache . 'settings' . DS . '*.php') ?: [],
			...\glob($cache . 'classmap.*.php') ?: [],
			...\glob($cache . 'scopes' . DS . '*' . DS . 'routes' . DS . '*.php') ?: [],
		];

		if (\is_file($list = self::preloadListFor(self::root()))) {
			$files[] = $list;
		}

		foreach ($files as $file) {
			\unlink($file);
		}

		return \count($files);
	}

	/**
	 * The release: the real path of the application's project directory, as the preload script finds
	 * it from its own.
	 */
	private static function root(): string
	{
		$root = \rtrim(app()->getProjectDir()->getRoot(), '/\\');

		return \realpath($root) ?: $root;
	}

	private static function write(string $file, string $code): void
	{
		$dir = \dirname($file);

		if (!\is_dir($dir) && !\mkdir($dir, 0o775, true) && !\is_dir($dir)) {
			throw new RuntimeException(\sprintf('Unable to create the directory "%s".', $dir));
		}

		(new FilesManager($dir))->writeAtomic(\basename($file), '<?php' . \PHP_EOL . \PHP_EOL . $code . \PHP_EOL);
	}
}
