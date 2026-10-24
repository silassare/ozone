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
use OZONE\Core\App\InstanceLayout;
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
 * All of it goes under `.ozone/build/` and is named after the release (the project directory):
 * `.ozone/` is shared by the releases of an `oz deploy` root, and a release must never read what
 * another compiled.
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
	 * `diagnostics` holds what that process printed besides its report, stdout and stderr together.
	 * A build that succeeds while printing something used to say nothing at all: PHP writes a startup
	 * warning, or one raised while no error handler of ours is installed, to one of those two streams,
	 * and dropping them left the last error at shutdown as the only trace, without the line that says
	 * why.
	 *
	 * @return array{
	 *     scope: string,
	 *     production: bool,
	 *     settings_bundles: int,
	 *     preload: list<string>,
	 *     diagnostics: list<string>,
	 * }
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

		/** @var array{scope: string, production: bool, settings_bundles: int, preload: list<string>} $report */
		$report = \json_decode($last, true, 512, \JSON_THROW_ON_ERROR);

		// the report is the last line of stdout; whatever came before it, the process had to say
		\array_pop($lines);

		$report['diagnostics'] = self::diagnostics($lines, $process->getErrorOutput());

		return $report;
	}

	/**
	 * The lines a scope build printed that are not its report.
	 *
	 * @param list<string> $out stdout without its last line, the report
	 * @param string       $err stderr
	 *
	 * @return list<string>
	 */
	private static function diagnostics(array $out, string $err): array
	{
		$kept = [];

		foreach ([...$out, ...(\preg_split('~\R~', $err) ?: [])] as $line) {
			$line = \trim($line);

			if ('' !== $line) {
				$kept[] = $line;
			}
		}

		return $kept;
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
		return InstanceLayout::preloadScriptPath(self::root());
	}

	/**
	 * The file listing what a release preloads, in that release's build directory.
	 *
	 * @param string $root the release: the real path of its project directory
	 */
	public static function preloadListFor(string $root): string
	{
		return InstanceLayout::releaseBuildPath($root, 'preload.php');
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
				$list = __DIR__ . '/%s/' . \hash('xxh128', $root) . '/preload.php';

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
			ReleaseLayout::CURRENT,
			InstanceLayout::BUILD
		);
	}

	/**
	 * Removes what builds and requests compiled: the next ones compile it again.
	 *
	 * This release's build directory and nothing else: never `.ozone/cache/`, which holds what
	 * requests filled on demand and which no build wrote, and never another release's, which this
	 * one has no business touching -- a live release whose compiled settings and class map were
	 * removed under it recompiles them mid-request, and the window where the file is gone is a
	 * window where it fails.
	 *
	 * The generated ORM classes are left alone although a build writes them, because nothing
	 * regenerates them on demand: `oz db build` does, and a request that needs a missing one only
	 * fails. The preload script stays too, since PHP refuses to start when `opcache.preload` points
	 * to nothing.
	 *
	 * @return int the files removed
	 */
	public static function clear(): int
	{
		$build = InstanceLayout::releaseBuildPath(self::root()) . DS;
		$files = [
			...\glob($build . InstanceLayout::BUILD_ENV . DS . '*.php') ?: [],
			...\glob($build . InstanceLayout::BUILD_SETTINGS . DS . '*.php') ?: [],
			...\glob($build . 'classmap.*.php') ?: [],
			...\glob($build . InstanceLayout::BUILD_ROUTES . DS . '*' . DS . '*.php') ?: [],
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
	 * Removes the build directories of releases that are gone.
	 *
	 * Only in a deploy root, where `.ozone/` is shared: a pruned release leaves its build directory
	 * behind and nothing else would ever remove it. What a release still on disk compiled is kept,
	 * whatever state that release is in -- it may be the live one, or the one a rollback goes back
	 * to -- and so is anything in `build/` that is not a release directory, which is what a
	 * deployment made before build output was keyed left there and what a release still running the
	 * older OZone reads.
	 *
	 * This runs as a step of a release, before the release that follows it prunes anything, so it
	 * collects what an earlier deploy removed: one deploy's worth of build output is orphaned at a
	 * time, never more. Pruning here and not in the deploy's own prune step is deliberate -- a
	 * project that is not deployed this way still has one release, itself, and nothing to remove.
	 *
	 * @param null|string $root the release to prune around, this one by default
	 *
	 * @return int the directories removed
	 */
	public static function pruneOrphans(?string $root = null): int
	{
		$root = null === $root ? self::root() : (\realpath($root) ?: $root);
		$dir  = \dirname(\rtrim($root, '/\\'));

		// A release sits in `{deploy root}/releases/{name}`; anything else is a project of its own,
		// which owns the whole of `build/` and has nothing to prune.
		if (ReleaseLayout::RELEASES !== \basename($dir) || !ReleaseLayout::isDeployRoot($deploy = \dirname($dir))) {
			return 0;
		}

		$keep = [];

		foreach (ReleaseLayout::releases($deploy) as $release) {
			$keep[InstanceLayout::releaseKey(ReleaseLayout::releaseDir($deploy, $release))] = true;
		}

		// Whatever this build is for, even a release directory not listed yet.
		$keep[InstanceLayout::releaseKey($root)] = true;

		$build   = InstanceLayout::buildPath($root);
		$orphans = [];

		foreach (\glob($build . DS . '*', \GLOB_ONLYDIR) ?: [] as $path) {
			$name = \basename($path);

			// A release key and nothing else: `env`, `settings`, `routes` and `plugins` directly
			// under `build/` are what a deployment made before this keying left there.
			if (!isset($keep[$name]) && \preg_match('#^[0-9a-f]{32}$#', $name)) {
				$orphans[] = $name;
			}
		}

		if (!$orphans) {
			return 0;
		}

		$fm = new FilesManager($build);

		foreach ($orphans as $name) {
			$fm->rmdir($name);
		}

		return \count($orphans);
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
