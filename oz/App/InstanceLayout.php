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

namespace OZONE\Core\App;

use OZONE\Core\FS\FilesManager;
use OZONE\Core\FS\FS;

/**
 * Class InstanceLayout.
 *
 * Where a project's own working files live, under `.ozone/`.
 *
 * `.ozone/` is per instance and may be deleted at any time; `data/` is the state that must survive
 * and be backed up (`Scopes\StateLayout`). Inside `.ozone/`, the split is by lifetime:
 *
 * ```
 * .ozone/
 *   build/
 *     {release}/ what `oz project build` produces, and a request fills in when a build has not run:
 *              the compiled `.env`, the settings bundles, the route tables, the class map, the
 *              preload list, the generated ORM classes. All of it is derived from the release's
 *              code, so a deployment may drop the whole directory: it costs a rebuild, never data.
 *   cache/     what a request fills on demand, per scope: image renditions, compiled templates,
 *              a file store's disposable entries. Droppable mid-flight, and nothing rebuilds it
 *              until something asks again.
 *   logs/      the only thing here worth reading after the fact.
 *   preload.php  the script `opcache.preload` points at: the same for every release, which is why
 *              it is not in `build/` -- PHP refuses to start when that path holds nothing.
 * ```
 *
 * Everything a build writes goes under `build/{release}/` ({@see self::releaseBuildPath()}), because
 * `.ozone/` is shared by the releases of an `oz deploy` root and a release must never read what
 * another compiled. The directory, not the file name, is what keys it: a build output added later is
 * keyed because of where it goes, not because whoever added it remembered to name it after the
 * release.
 */
final class InstanceLayout
{
	/**
	 * The directory itself, under a project.
	 */
	public const string DIR = '.ozone';

	/**
	 * Build output: derived from the release's code, and dropping it costs only a rebuild.
	 */
	public const string BUILD = 'build';

	/**
	 * Caches a request fills on demand, and that may be dropped mid-flight.
	 */
	public const string CACHE = 'cache';

	/**
	 * Logs.
	 */
	public const string LOGS = 'logs';

	/**
	 * The script `opcache.preload` points at, directly under {@see self::DIR}.
	 */
	public const string PRELOAD_SCRIPT = 'preload.php';

	/**
	 * Build output: the compiled `.env` files.
	 */
	public const string BUILD_ENV = 'env';

	/**
	 * Build output: the settings bundle of each source directory.
	 */
	public const string BUILD_SETTINGS = 'settings';

	/**
	 * Build output: the route table of each scope, under one directory per state slug.
	 */
	public const string BUILD_ROUTES = 'routes';

	/**
	 * Build output: the ORM classes generated for OZone and for each plugin.
	 */
	public const string BUILD_PLUGINS = 'plugins';

	/**
	 * The marker telling a request whether it is the first of its minute to look at the schedule.
	 *
	 * Project-wide and not per scope: whichever scope serves the request, there is one schedule.
	 */
	public const string CACHE_CRON_MARKER = 'cron.minute';

	/**
	 * `{root}/.ozone`, with the parts under it joined on.
	 *
	 * @param string $root a project directory
	 */
	public static function path(string $root, string ...$parts): string
	{
		return \implode(DS, [\rtrim($root, '/\\') . DS . self::DIR, ...$parts]);
	}

	/**
	 * The directory name that holds one release's build output.
	 *
	 * The release's real path and not the path as given: a release is reached as itself and through
	 * `current`, and the two spellings must name one directory. Outside a deploy root there is one
	 * release, the project itself, and this names it.
	 *
	 * @param string $root a project directory
	 */
	public static function releaseKey(string $root): string
	{
		static $keys = [];

		$root = \rtrim($root, '/\\');

		// Every path under `build/` goes through here, several times a request: a release does not
		// move under a running process, so the answer is computed once per spelling of it.
		return $keys[$root] ??= \hash('xxh128', \realpath($root) ?: $root);
	}

	/**
	 * A path under this release's build directory: `.ozone/build/{release}/...`.
	 *
	 * What every build output goes through. The shape of what a build writes depends on the versions
	 * in the release's `vendor/` -- the generated ORM classes most of all, whose constants and
	 * signatures come from Gobl and the code generator installed there -- so two releases on disk at
	 * once cannot share one directory, and a release reads only what its own `vendor/` produced.
	 *
	 * @param string $root a project directory
	 */
	public static function releaseBuildPath(string $root, string ...$parts): string
	{
		return self::buildPath($root, self::releaseKey($root), ...$parts);
	}

	/**
	 * A directory under this release's build directory, created when missing.
	 *
	 * @param string $root a project directory
	 */
	public static function releaseBuildDir(string $root, string ...$parts): FilesManager
	{
		return self::buildDir($root, self::releaseKey($root), ...$parts);
	}

	/**
	 * A path under `.ozone/build/`, holding every release's build directory.
	 *
	 * A build output belongs under {@see self::releaseBuildPath()}, never directly here.
	 *
	 * @param string $root a project directory
	 */
	public static function buildPath(string $root, string ...$parts): string
	{
		return self::path($root, self::BUILD, ...$parts);
	}

	/**
	 * A path under `.ozone/cache/`.
	 *
	 * @param string $root a project directory
	 */
	public static function cachePath(string $root, string ...$parts): string
	{
		return self::path($root, self::CACHE, ...$parts);
	}

	/**
	 * The script `opcache.preload` points at.
	 *
	 * @param string $root a project directory, or the root of a deploy
	 */
	public static function preloadScriptPath(string $root): string
	{
		return self::path($root, self::PRELOAD_SCRIPT);
	}

	/**
	 * A directory under `.ozone/build/`, created when missing.
	 *
	 * @param string $root a project directory
	 */
	public static function buildDir(string $root, string ...$parts): FilesManager
	{
		return self::dirAt($root, self::BUILD, ...$parts);
	}

	/**
	 * A scope's cache directory, created when missing: `.ozone/cache/{scope}`.
	 *
	 * The slug is `ScopeInterface::getStateSlug()`, the same one that names the scope's state under
	 * `data/`, so the two roots read alike.
	 *
	 * @param string $root a project directory
	 * @param string $slug the scope's state slug
	 */
	public static function scopeCacheDir(string $root, string $slug): FilesManager
	{
		return self::dirAt($root, self::CACHE, $slug);
	}

	/**
	 * The logs directory, created when missing.
	 *
	 * @param string $root a project directory
	 */
	public static function logsDir(string $root): FilesManager
	{
		return self::dirAt($root, self::LOGS);
	}

	/**
	 * A directory under `.ozone/`, created when missing.
	 *
	 * A fresh manager, never a caller's: `FilesManager::cd()` moves one in place.
	 *
	 * @param string $root a project directory
	 */
	private static function dirAt(string $root, string ...$parts): FilesManager
	{
		return FS::from($root)->cd(\implode(DS, [self::DIR, ...$parts]), true);
	}
}
