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
	 * @return array{scope: string, production: bool, settings_bundles: int, preload: list<string>}
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
			'scope'            => $scope,
			'production'       => OZone::inProductionMode(),
			'settings_bundles' => 0,
			'preload'          => [],
		];

		if (!$report['production']) {
			return $report;
		}

		$report['settings_bundles'] = Settings::warmBundles();

		// Each compiles its route table, when this release has none yet.
		OZone::getApiRouter();
		OZone::getWebRouter();

		self::declareFirstParty();

		$report['preload'] = self::preloadable();

		return $report;
	}

	/**
	 * Declares the classes of OZone and of its first-party packages (`silassare/*`, `psr/*`), so the
	 * preload list holds what a request may load, and not only what booting and building the routers
	 * did: the request path, Gobl's ORM runtime, the template engine.
	 *
	 * Left out: the command line (`OZONE\Core\Cli\`, Kli), tests, the generated ORM classes, and a
	 * file that does not declare the class its path names. A class that cannot be declared -- an
	 * optional integration whose package is missing -- is left out too.
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

		/** @var SplFileInfo $file */
		foreach ($files as $file) {
			if ('php' !== $file->getExtension()) {
				continue;
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
				. \preg_quote((string) \end($segments), '~') . '\b~m';

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
		$skip_dirs = [OZ_PROJECT_DIR . '.ozone' . DS, $vendor . 'composer' . DS];
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
}
