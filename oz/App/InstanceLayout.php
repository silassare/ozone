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
 *   build/     what `oz project build` produces, and a request fills in when a build has not run:
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
 * What a build writes is named after the release, because `.ozone/` is shared by the releases of an
 * `oz deploy` root and a release must never read what another compiled.
 */
final class InstanceLayout
{
	/**
	 * The directory itself, under a project.
	 */
	public const DIR = '.ozone';

	/**
	 * Build output: derived from the release's code, and dropping it costs only a rebuild.
	 */
	public const BUILD = 'build';

	/**
	 * Caches a request fills on demand, and that may be dropped mid-flight.
	 */
	public const CACHE = 'cache';

	/**
	 * Logs.
	 */
	public const LOGS = 'logs';

	/**
	 * The script `opcache.preload` points at, directly under {@see self::DIR}.
	 */
	public const PRELOAD_SCRIPT = 'preload.php';

	/**
	 * Build output: the compiled `.env` files.
	 */
	public const BUILD_ENV = 'env';

	/**
	 * Build output: the settings bundle of each source directory.
	 */
	public const BUILD_SETTINGS = 'settings';

	/**
	 * Build output: the route table of each scope, under one directory per state slug.
	 */
	public const BUILD_ROUTES = 'routes';

	/**
	 * Build output: the ORM classes generated for OZone and for each plugin.
	 */
	public const BUILD_PLUGINS = 'plugins';

	/**
	 * The marker telling a request whether it is the first of its minute to look at the schedule.
	 *
	 * Project-wide and not per scope: whichever scope serves the request, there is one schedule.
	 */
	public const CACHE_CRON_MARKER = 'cron.minute';

	/**
	 * `{root}/.ozone`, with the parts under it joined on.
	 *
	 * @param string $root  a project directory
	 * @param string ...$parts
	 */
	public static function path(string $root, string ...$parts): string
	{
		return \implode(DS, [\rtrim($root, '/\\') . DS . self::DIR, ...$parts]);
	}

	/**
	 * A path under `.ozone/build/`.
	 *
	 * @param string $root  a project directory
	 * @param string ...$parts
	 */
	public static function buildPath(string $root, string ...$parts): string
	{
		return self::path($root, self::BUILD, ...$parts);
	}

	/**
	 * A path under `.ozone/cache/`.
	 *
	 * @param string $root  a project directory
	 * @param string ...$parts
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
	 * @param string $root  a project directory
	 * @param string ...$parts
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
	 * @param string $root  a project directory
	 * @param string ...$parts
	 */
	private static function dirAt(string $root, string ...$parts): FilesManager
	{
		return FS::from($root)->cd(\implode(DS, [self::DIR, ...$parts]), true);
	}
}
