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

namespace OZONE\Core\Testing;

use Composer\Autoload\ClassLoader;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\ORM;
use OZONE\Core\App\Db;
use OZONE\Core\App\Keys;
use OZONE\Core\App\Settings;
use OZONE\Core\Migrations\Migrations;
use OZONE\Core\OZone;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Class Sandbox.
 *
 * A throwaway project a test suite boots {@see SandboxApp} on: its own `.env`, `data/`, `.ozone/`,
 * generated ORM classes and SQLite database, so nothing reads or writes the repository the suite runs
 * from. OZone's unit suite and worker servers use it; so can a plugin's, which enables itself through
 * a settings source (`oz.plugins`).
 *
 * The settings sources given to {@see create()} and to {@see bootstrap()} must be the same: the build
 * process and the suite must see the same schema.
 */
final class Sandbox
{
	/**
	 * Creates the sandbox project in `$dir`.
	 *
	 * @param string       $dir              the project directory, created if missing
	 * @param bool         $installed        whether the schema is installed through a migration, as in
	 *                                       a deployed project (see sandbox_build.php)
	 * @param list<string> $settings_sources settings directories added after the sandbox's own
	 */
	public static function create(string $dir, bool $installed = false, array $settings_sources = []): void
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

		// Services the environment provides, e.g. the Redis, MinIO and ClamAV containers of a
		// Docker test setup. Shared with OZTestProject, which needs exactly the same set.
		$env += ServiceEnv::fromEnvironment();

		$lines = '';

		foreach ($env as $key => $value) {
			$lines .= $key . '="' . $value . '"' . \PHP_EOL;
		}

		\file_put_contents($dir . '.env', $lines);

		// The ORM classes are generated in the project (`.ozone/plugins/`), as `oz db build` does. A
		// separate process builds them, so whoever boots next finds the classes present.
		$build = [\PHP_BINARY, __DIR__ . \DIRECTORY_SEPARATOR . 'sandbox_build.php', self::autoloadFile(), $dir];

		if ($installed) {
			$build[] = '--installed';
		}

		foreach ($settings_sources as $source) {
			$build[] = '--settings=' . $source;
		}

		(new Process($build))->mustRun();
	}

	/**
	 * Adds the sandbox's settings sources, then bootstraps OZone on a {@see SandboxApp} of `$dir`.
	 *
	 * @param string       $dir              the sandbox project directory
	 * @param list<string> $settings_sources settings directories added after the sandbox's own
	 */
	public static function bootstrap(string $dir, array $settings_sources = []): void
	{
		self::addSettingsSources($settings_sources);

		OZone::bootstrap(new SandboxApp($dir));
	}

	/**
	 * Builds the bootstrapped sandbox: the ORM classes of every enabled ORM namespace, then the schema.
	 * Run by sandbox_build.php, in the process {@see create()} starts.
	 *
	 * @param bool $installed whether the schema is installed through a migration
	 */
	public static function build(bool $installed): void
	{
		$db  = db();
		$gen = new CSGeneratorORM($db);

		$gen->ignorePrivateTables(false);
		$gen->ignorePrivateColumns(false);

		foreach (Db::ormNamespaces() as $ns) {
			$tables = $db->getTables($ns);

			if (!empty($tables)) {
				$gen->generate($tables, ORM::getOutputDir($ns));
			}
		}

		if ($installed) {
			$mg = new Migrations();

			$mg->create(true, 'sandbox');
			$mg->install($mg->getLatestMigration());

			return;
		}

		// The schema, in the sandbox SQLite database (settings/oz.db.php).
		$sql = \trim($db->getGenerator()->buildDatabase());

		if ('' !== $sql) {
			$db->executeMulti($sql);
		}
	}

	/**
	 * Adds the sandbox's own settings source (SQLite in the project directory), then `$settings_sources`,
	 * so theirs win. For a suite that builds its app itself; {@see bootstrap()} calls it.
	 *
	 * @param list<string> $settings_sources
	 */
	public static function addSettingsSources(array $settings_sources = []): void
	{
		Settings::addSource(__DIR__ . \DIRECTORY_SEPARATOR . 'settings');

		foreach ($settings_sources as $source) {
			Settings::addSource($source);
		}
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

	/**
	 * The Composer autoloader of the running process, for the build subprocess: OZone may be the root
	 * package or a dependency (`vendor/silassare/ozone`).
	 */
	private static function autoloadFile(): string
	{
		$loader_file = (new ReflectionClass(ClassLoader::class))->getFileName();

		if (false === $loader_file) {
			throw new RuntimeException('Unable to locate the Composer autoloader.');
		}

		return \dirname($loader_file, 2) . \DIRECTORY_SEPARATOR . 'autoload.php';
	}
}
