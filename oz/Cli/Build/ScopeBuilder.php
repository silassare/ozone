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

use FilesystemIterator;
use OZONE\Core\App\Db;
use OZONE\Core\App\InstanceLayout;
use OZONE\Core\App\Interfaces\AppInterface;
use OZONE\Core\App\Settings;
use OZONE\Core\FS\Assets;
use OZONE\Core\OZone;
use OZONE\Core\Runtime\Runtime;
use OZONE\Core\Runtime\WorkerRuntime;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Throwable;

/**
 * Prepares one scope of a project for production, in a process of its own (`oz project build` runs
 * one per scope): a scope is chosen by a constant and by the settings sources its entry point adds,
 * both set once per process.
 *
 * It sets the scope up as its `index.php` does (`scope_build.php`), then runs as a request would --
 * outside the console, as a worker since there is no request -- so what it compiles is named as a
 * request looks it up: the compiled `.env`, the settings bundles, the route tables.
 *
 * @internal
 */
final class ScopeBuilder
{
	/**
	 * One descriptor count every so many files of the pass that opens the most of them
	 * ({@see self::declareUnder()}).
	 */
	private const int DESCRIPTOR_SAMPLE_EVERY = 64;

	/**
	 * The most descriptors this process has been seen holding: {@see self::sampleDescriptors()}.
	 */
	private static int $fd_peak = 0;

	/**
	 * Builds one scope and reports what this release has compiled against what it asked for: a
	 * settings directory that cannot be bundled and a route table that cannot be written both leave
	 * the work for requests to redo instead of failing, so a count on its own never says whether the
	 * build is whole.
	 *
	 * It reports the descriptors it held at its highest against the limit it ran under, which is the
	 * one question a build that runs out of them cannot answer afterwards: a process that can open no
	 * more files reports what it could not read as something it could not compile, and the limit it
	 * ran under is whatever started it -- a deploy's shell, a systemd unit, a cron job -- and not what
	 * an interactive shell reports later.
	 *
	 * @return array{
	 *     scope: string,
	 *     production: bool,
	 *     settings_bundles: int,
	 *     settings_sources: int,
	 *     settings_failed: array<string, string>,
	 *     route_tables: int,
	 *     route_tables_failed: list<string>,
	 *     preload: list<string>,
	 *     descriptors: array{peak: null|int, limit: null|int, hard: null|int},
	 * }
	 */
	public static function run(AppInterface $app, string $scope): array
	{
		$dir = OZ_PROJECT_DIR . 'scopes' . DS . $scope;

		if (\is_dir($dir . DS . 'settings')) {
			Settings::addSource($dir . DS . 'settings');
		}

		if (\is_dir($dir . DS . 'templates')) {
			Assets::addSource($dir . DS . 'templates');
		}

		Runtime::set(new WorkerRuntime('oz-project-build'));

		OZone::bootstrap($app);

		$report = [
			'scope'               => $scope,
			'production'          => OZone::inProductionMode(),
			'settings_bundles'    => 0,
			'settings_sources'    => 0,
			'settings_failed'     => [],
			'route_tables'        => 0,
			'route_tables_failed' => [],
			'preload'             => [],
			// The first sample is taken here, so a scope that is not built in this mode still
			// reports what booting alone held.
			'descriptors'         => self::descriptors(),
		];

		if (!$report['production']) {
			return $report;
		}

		$bundles = Settings::warmBundles();

		$report['settings_bundles'] = $bundles['bundled'];
		$report['settings_sources'] = $bundles['expected'];
		$report['settings_failed']  = $bundles['failed'];

		self::sampleDescriptors();

		// Each compiles its route table, when this release has none yet.
		OZone::getApiRouter();
		OZone::getWebRouter();

		self::sampleDescriptors();

		foreach (OZone::routeTableBuilds() as $build) {
			if ($build['compiled']) {
				++$report['route_tables'];
			} else {
				$report['route_tables_failed'][] = $build['file'];
			}
		}

		self::declareFirstParty();

		$report['preload']     = self::preloadable();
		$report['descriptors'] = self::descriptors();

		return $report;
	}

	/**
	 * Declares the classes of OZone and of its first-party packages (`silassare/*`, `psr/*`), so the
	 * preload list holds what a request may load, and not only what booting and building the routers
	 * did: the request path, Gobl's ORM runtime, the template engine.
	 *
	 * Left out: the command line (`OZONE\Core\Cli\`, Kli), tests and the test kit
	 * (`OZONE\Core\Testing\`), the generated ORM classes, and a file that does not declare the class its
	 * path names. A class that cannot be declared -- an optional integration whose package is missing --
	 * is left out too.
	 *
	 * Declaring them is what makes the list usable, not a detour: preloading only *links* a class
	 * whose parents, interfaces and traits are already preloaded, so the list has to be emitted
	 * parents-first with anything unlinkable dropped ({@see self::preloadable()}), and that order
	 * comes from Reflection, which only sees what is declared. The cost is one build-time pass over
	 * about a thousand first-party files.
	 *
	 * Taking this far rather than stopping at what booting and the routers declared is measured, not
	 * assumed: 750 files against 240 buys 13% on the text route and 11% on the database route in
	 * classic mode (`docker/bench/README.md`, 2026-11-01).
	 */
	private static function declareFirstParty(): void
	{
		$vendor = OZ_PROJECT_DIR . 'vendor' . DS;
		$psr4   = $vendor . 'composer' . DS . 'autoload_psr4.php';

		if (!\is_file($psr4)) {
			return;
		}

		$roots = [];

		foreach ([$vendor . 'silassare', $vendor . 'psr', OZ_OZONE_DIR] as $root) {
			if (false !== ($real = \realpath($root))) {
				$roots[] = $real . DS;
			}
		}

		$skip = [
			'Kli\\',
			'OZONE\Core\Cli\\',
			'OZONE\Core\Testing\\',
			...\array_map(static fn (string $ns): string => $ns . '\\', Db::ormNamespaces()),
		];

		foreach ((array) require $psr4 as $prefix => $dirs) {
			if (!\is_string($prefix) || \str_contains($prefix, '\Tests\\')) {
				continue;
			}

			foreach ((array) $dirs as $dir) {
				$dir = \realpath((string) $dir);

				foreach ($roots as $root) {
					if (false !== $dir && \str_starts_with($dir . DS, $root)) {
						self::declareUnder($prefix, $dir, $skip);

						break;
					}
				}
			}
		}
	}

	/**
	 * Declares the classes of one PSR-4 directory.
	 *
	 * @param list<string> $skip namespaces left out
	 */
	private static function declareUnder(string $prefix, string $dir, array $skip): void
	{
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
		$seen  = 0;

		/** @var SplFileInfo $file */
		foreach ($files as $file) {
			if ('php' !== $file->getExtension()) {
				continue;
			}

			// Watched from the inside, this one: it is the pass that opens the most files, so a
			// descriptor that is opened here and never released is seen climbing rather than only
			// counted once it is all over ({@see self::sampleDescriptors()}).
			if (0 === ++$seen % self::DESCRIPTOR_SAMPLE_EVERY) {
				self::sampleDescriptors();
			}

			// PascalCase segments only: `oz_default/`, `bootstrap.php` and scripts name no class.
			$segments = \explode(DS, \substr($file->getPathname(), \strlen($dir) + 1, -4));

			foreach ($segments as $segment) {
				if (!\preg_match('~^[A-Z]\w*$~', $segment)) {
					continue 2;
				}
			}

			$class = $prefix . \implode('\\', $segments);

			foreach ($skip as $ns) {
				if (\str_starts_with($class, $ns)) {
					continue 2;
				}
			}

			$pattern = '~^\s*(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s+'
				. \preg_quote((string) \array_last($segments), '~') . '\b~m';

			if (!\preg_match($pattern, (string) \file_get_contents($file->getPathname()))) {
				continue;
			}

			try {
				// the autoloader declares whatever the file holds: class, interface, trait or enum
				\class_exists($class);
			} catch (Throwable) {
				// needs what is not installed: not preloaded
			}
		}
	}

	/**
	 * The files of the classes this process declared that preloading can compile, parents first.
	 *
	 * Left out: what is under `.ozone/`, Composer's own files and its `files` entries (included by
	 * every request: preloaded, their functions would be declared twice), the generated ORM classes
	 * (their first use initializes the database: {@see Db::initOnFirstUse()}), and whatever extends
	 * or implements what is left out, which preloading could not link.
	 *
	 * @return list<string>
	 */
	private static function preloadable(): array
	{
		$vendor    = OZ_PROJECT_DIR . 'vendor' . DS;
		$skip_dirs = [InstanceLayout::path(OZ_PROJECT_DIR) . DS, $vendor . 'composer' . DS];
		$skip_ns   = \array_map(static fn (string $ns): string => $ns . '\\', Db::ormNamespaces());
		$skip      = \is_file($vendor . 'composer' . DS . 'autoload_files.php')
			? \array_fill_keys(\array_values((array) require $vendor . 'composer' . DS . 'autoload_files.php'), true)
			: [];

		$files = [];
		$state = [];

		$visit = static function (ReflectionClass $class) use (&$visit, &$files, &$state, $skip_dirs, $skip_ns, $skip) {
			$name = $class->getName();

			if (isset($state[$name])) {
				return $state[$name];
			}

			if ($class->isInternal()) {
				return $state[$name] = true;
			}

			// until its dependencies are known, and against a cycle
			$state[$name] = false;

			$file = (string) $class->getFileName();

			if ('' === $file || $class->isAnonymous() || isset($skip[$file])) {
				return false;
			}

			foreach ($skip_dirs as $dir) {
				if (\str_starts_with($file, $dir)) {
					return false;
				}
			}

			foreach ($skip_ns as $ns) {
				if (\str_starts_with($name, $ns)) {
					return false;
				}
			}

			$parent = $class->getParentClass();

			if (false !== $parent && !$visit($parent)) {
				return false;
			}

			foreach ([...$class->getInterfaces(), ...$class->getTraits()] as $dependency) {
				if (!$visit($dependency)) {
					return false;
				}
			}

			$files[$file] = true;

			return $state[$name] = true;
		};

		foreach ([...\get_declared_classes(), ...\get_declared_interfaces(), ...\get_declared_traits()] as $name) {
			$visit(new ReflectionClass($name));
		}

		return \array_keys($files);
	}

	/**
	 * Counts the descriptors this process holds, keeping the highest it has seen
	 * ({@see self::descriptors()}).
	 *
	 * Sampled and not watched: PHP offers no hook on opening a file, so this is called at the
	 * boundaries of the build's phases and through the pass that opens the most files. What it is
	 * there to catch is a count that climbs and never comes back down -- a descriptor held and never
	 * closed -- and not a spike inside a single call, which it can miss.
	 *
	 * Linux only: `/proc/self/fd` lists a process's own descriptors and nothing portable does, so
	 * the peak is simply unknown elsewhere.
	 */
	private static function sampleDescriptors(): void
	{
		$open = @\scandir('/proc/self/fd');

		if (false === $open) {
			return;
		}

		// Less "." and "..", and less the directory handle this very count is reading through, which
		// /proc lists like any other.
		$held = \count($open) - 3;

		if ($held > self::$fd_peak) {
			self::$fd_peak = $held;
		}
	}

	/**
	 * The most descriptors this process was seen holding, and the limits it runs under.
	 *
	 * `peak` is null where the platform does not say ({@see self::sampleDescriptors()}), `limit` and
	 * `hard` where the limit is not a number -- "unlimited", which no Linux host sets for open files.
	 *
	 * @return array{peak: null|int, limit: null|int, hard: null|int}
	 */
	private static function descriptors(): array
	{
		self::sampleDescriptors();

		/** @var array<string, mixed> $limits */
		$limits = \posix_getrlimit() ?: [];

		$number = static fn (mixed $value): ?int => \is_int($value)
			|| (\is_string($value) && \ctype_digit($value)) ? (int) $value : null;

		return [
			'peak'  => self::$fd_peak > 0 ? self::$fd_peak : null,
			'limit' => $number($limits['soft openfiles'] ?? null),
			'hard'  => $number($limits['hard openfiles'] ?? null),
		];
	}
}
