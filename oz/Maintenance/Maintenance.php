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

namespace OZONE\Core\Maintenance;

use JsonException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Scopes\StateLayout;

/**
 * Class Maintenance.
 *
 * Whether the instance is under maintenance: one file, `data/root/state/maintenance.json`, written
 * by `oz maintenance on` and removed by `oz maintenance off`.
 *
 * A file and not a setting: a setting written at runtime compiles new settings bundles and a new
 * route table, where turning maintenance on must change nothing but the answer. Under `data/` and
 * not under `.ozone/`: `.ozone/` may be deleted at any time, and maintenance lifted by a cache
 * cleanup in the middle of a migration is the failure this exists to prevent; and every instance
 * sharing `data/` shares the database a maintenance protects, so they are held together. The
 * application's own state directory, whatever scope asks: one maintenance holds every scope it
 * names, and a scope that keeps its state apart would not see it.
 *
 * Read on every request, so reading it is one stat while it is off, and never a cached answer: a
 * worker serving requests for hours sees `on` and `off` on its next request.
 */
final class Maintenance
{
	/**
	 * The name of the file, in the application's state directory.
	 */
	public const string FILE = 'maintenance.json';

	/**
	 * The path of the file, once resolved: the directory is the process's, not a request's.
	 */
	private static ?string $file = null;

	/**
	 * The path of the file.
	 */
	public static function file(): string
	{
		return self::$file ??= StateLayout::path(app(), StateLayout::STATE) . DS . self::FILE;
	}

	/**
	 * The maintenance in force, or null when there is none.
	 *
	 * A file that is there and cannot be read is a maintenance holding everything
	 * ({@see MaintenanceMode::unreadable()}), never none.
	 */
	public static function current(): ?MaintenanceMode
	{
		$file = self::file();

		// A worker keeps PHP's stat cache from one request to the next: the answer of the last one
		// would hold this one too.
		\clearstatcache(true, $file);

		if (!\is_file($file)) {
			return null;
		}

		$json = @\file_get_contents($file);

		if (false === $json) {
			\clearstatcache(true, $file);

			// Gone between the two calls: turned off meanwhile.
			return \is_file($file)
				? MaintenanceMode::unreadable('the file cannot be read', self::mtime($file))
				: null;
		}

		$mode = MaintenanceMode::fromJson($json);

		if ($mode->isReadable()) {
			return $mode;
		}

		return MaintenanceMode::unreadable((string) $mode->fault, self::mtime($file));
	}

	/**
	 * Whether cron and the queued jobs wait: a maintenance asked for it, or its record cannot be read.
	 */
	public static function jobsPaused(): bool
	{
		return true === self::current()?->pauseJobs;
	}

	/**
	 * Puts a maintenance in force, replacing the one in force if any.
	 *
	 * Written whole or not at all (`FilesManager::writeAtomic()`): a request reading the file while
	 * it is written reads the old record or the new one.
	 *
	 * @throws RuntimeException when it cannot be written
	 */
	public static function enable(MaintenanceMode $mode): void
	{
		try {
			$json = \json_encode(
				$mode->toArray(),
				\JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES
			);
		} catch (JsonException $e) {
			throw new RuntimeException('Unable to encode the maintenance record.', null, $e);
		}

		StateLayout::dir(app(), StateLayout::STATE)->writeAtomic(self::FILE, $json . \PHP_EOL);
	}

	/**
	 * Ends the maintenance in force.
	 *
	 * @return bool whether there was one
	 *
	 * @throws RuntimeException when the file is there and cannot be removed
	 */
	public static function disable(): bool
	{
		$file = self::file();

		\clearstatcache(true, $file);

		if (!\is_file($file)) {
			return false;
		}

		if (!@\unlink($file) && \is_file($file)) {
			throw new RuntimeException(\sprintf('Unable to remove "%s": maintenance is still on.', $file));
		}

		return true;
	}

	private static function mtime(string $file): ?int
	{
		$mtime = @\filemtime($file);

		return false === $mtime ? null : $mtime;
	}
}
