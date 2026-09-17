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

namespace OZONE\Tests\Support;

use OZONE\Core\App\Keys;
use OZONE\Tests\App;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Process;

/**
 * Class Sandbox.
 *
 * A throwaway project for the unit suite (tests/autoload.php) and for the worker servers of
 * tests/Runtime/Servers/, which serve {@see App} from one: its own `.env`, `data/`,
 * `.ozone/` and generated ORM classes, so nothing reads or writes the repository's.
 */
final class Sandbox
{
	/**
	 * Creates the sandbox project in `$dir`.
	 *
	 * @param string $dir       the project directory, created if missing
	 * @param bool   $installed whether the schema is installed through a migration, as in a deployed
	 *                          project (see tests/sandbox_build.php)
	 */
	public static function create(string $dir, bool $installed = false): void
	{
		$dir = \rtrim($dir, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR;

		// `data/` is the volume a deployment mounts, and OZone refuses to create it: the sandbox
		// stands in for what `oz project create` does. Everything inside it is created on demand.
		if (!\is_dir($dir . 'data')) {
			\mkdir($dir . 'data', 0o775, true);
		}

		$env = [
			'OZ_APP_SALT'   => \base64_encode(Keys::newSalt()),
			'OZ_APP_SECRET' => \base64_encode(Keys::newSecret()),
		];

		// Services the environment provides, e.g. the Redis, MinIO and ClamAV containers of
		// docker/compose.yaml. Shared with the integration suite, which needs exactly the same set.
		$env += ServiceEnv::fromEnvironment();

		$lines = '';

		foreach ($env as $key => $value) {
			$lines .= $key . '="' . $value . '"' . \PHP_EOL;
		}

		\file_put_contents($dir . '.env', $lines);

		// OZone's ORM classes are generated in the project (`.ozone/plugins/`), as `oz db build`
		// does. A separate process builds them, so whoever boots next finds the classes present.
		$build = [\PHP_BINARY, \dirname(__DIR__) . '/sandbox_build.php', $dir];

		if ($installed) {
			$build[] = 'installed';
		}

		(new Process($build))->mustRun();
	}

	/**
	 * Removes a sandbox project, if there is one in `$dir`.
	 */
	public static function remove(string $dir): void
	{
		if (!\is_dir($dir)) {
			return;
		}

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($files as $file) {
			$file->isDir() && !$file->isLink() ? @\rmdir($file->getPathname()) : @\unlink($file->getPathname());
		}

		@\rmdir($dir);
	}
}
