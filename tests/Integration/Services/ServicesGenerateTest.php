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

namespace OZONE\Tests\Integration\Services;

use OZONE\Tests\Integration\Support\DbTestConfig;
use OZONE\Tests\Integration\Support\OZTestProject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Integration tests for `oz services generate`.
 *
 * Runs against every configured DB type via @dataProvider.
 * SQLite is always tested. MySQL / PostgreSQL are tested when their env vars
 * are set (see DbTestConfig::allConfigured()).
 *
 * Each DB type uses its own isolated project directory and independent test
 * chain. Setup order: `oz db build --build-all --class-only` + migrations run
 * MUST precede `oz services generate`.
 *
 * @internal
 *
 * @coversNothing
 */
final class ServicesGenerateTest extends TestCase
{
	/** The OZone built-in public table we generate a service for. */
	private const TABLE = 'oz_countries';

	/** Expected generated class name (derived from table name). */
	private const SERVICE_CLASS = 'OzCountriesService';

	/**
	 * Projects created per RDBMS, kept for tearDownAfterClass.
	 *
	 * @var array<string, OZTestProject>
	 */
	private static array $projects = [];

	/**
	 * SQLite database file per RDBMS key (null for server-based RDBMS).
	 *
	 * @var array<string, null|string>
	 */
	private static array $dbFiles = [];

	public static function tearDownAfterClass(): void
	{
		foreach (self::$projects as $key => $proj) {
			$proj->destroy();
			$file = self::$dbFiles[$key] ?? null;
			if (null !== $file && \is_file($file)) {
				\unlink($file);
			}
		}
		self::$projects = [];
		self::$dbFiles  = [];
		parent::tearDownAfterClass();
	}

	/**
	 * Creates the test project, sets up DB & ORM classes, runs services generate,
	 * and asserts the command exits cleanly.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testGenerateExitsCleanly(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('svcgen-' . $rdbms, shared: false);
		$proj->writeEnv($config->toEnvArray());

		self::$projects[$rdbms] = $proj;
		self::$dbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;

		// ORM classes must exist before migrations can run.
		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		// DB must be installed for services generate to access the table schema.
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		$proc = $proj->oz(
			'services',
			'generate',
			'--table=' . self::TABLE,
			'--class=' . self::SERVICE_CLASS,
			'--base-path=/countries',
		);
		$proc->mustRun();
		self::assertSame(0, $proc->getExitCode(), $proc->getErrorOutput());
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testGeneratedFileExists(DbTestConfig $config): void
	{
		$file = self::getProject($config)->getPath() . '/app/Services/' . self::SERVICE_CLASS . '.php';
		self::assertFileExists($file, 'Generated service file must exist at app/Services/.');
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testGeneratedFileIsValidPhp(DbTestConfig $config): void
	{
		$file = self::getProject($config)->getPath() . '/app/Services/' . self::SERVICE_CLASS . '.php';
		$lint = new Process([\PHP_BINARY, '-l', $file]);
		$lint->run();
		self::assertSame(0, $lint->getExitCode(), 'PHP syntax error in generated service: ' . $lint->getOutput());
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testGeneratedFileContainsClassName(DbTestConfig $config): void
	{
		$file    = self::getProject($config)->getPath() . '/app/Services/' . self::SERVICE_CLASS . '.php';
		$content = \file_get_contents($file);
		self::assertStringContainsString('class ' . self::SERVICE_CLASS, (string) $content);
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testServiceIsRegisteredInRoutesSettings(DbTestConfig $config): void
	{
		// oz services generate calls Settings::set('oz.routes.api', ...).
		// The setting lands in app/settings/oz.routes.api.php.
		$settingsFile = self::getProject($config)->getPath() . '/app/settings/oz.routes.api.php';
		self::assertFileExists($settingsFile, 'oz.routes.api.php must be written by services generate.');

		$settings = require $settingsFile;
		self::assertIsArray($settings);

		// At least one entry that references our service class should be present.
		$found = false;

		foreach (\array_keys($settings) as $key) {
			if (\str_ends_with((string) $key, self::SERVICE_CLASS)) {
				$found = true;

				break;
			}
		}
		self::assertTrue($found, self::SERVICE_CLASS . ' must appear in oz.routes.api settings.');
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testGenerateWithoutOverrideFails(DbTestConfig $config): void
	{
		// File already exists from testGenerateExitsCleanly; without --override it must fail.
		$proc = self::getProject($config)->oz(
			'services',
			'generate',
			'--table=' . self::TABLE,
			'--class=' . self::SERVICE_CLASS,
			'--base-path=/countries',
		);
		$proc->run();
		self::assertNotSame(0, $proc->getExitCode(), 'Re-generating without --override must fail when file exists.');
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testGenerateWithOverrideSucceeds(DbTestConfig $config): void
	{
		$proj = self::getProject($config);
		$proc = $proj->oz(
			'services',
			'generate',
			'--table=' . self::TABLE,
			'--class=' . self::SERVICE_CLASS,
			'--base-path=/countries',
			'--override',
		);
		$proc->mustRun();
		self::assertSame(0, $proc->getExitCode(), $proc->getErrorOutput());

		// File must still be valid PHP after the overwrite.
		$file = $proj->getPath() . '/app/Services/' . self::SERVICE_CLASS . '.php';
		$lint = new Process([\PHP_BINARY, '-l', $file]);
		$lint->run();
		self::assertSame(0, $lint->getExitCode(), $lint->getOutput());
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testGenerateForNonExistentTableFails(DbTestConfig $config): void
	{
		$proc = self::getProject($config)->oz(
			'services',
			'generate',
			'--table=no_such_table_xyz',
			'--base-path=/nope',
		);
		$proc->run();
		self::assertNotSame(0, $proc->getExitCode(), 'Generate for a non-existent table must exit with an error.');
	}

	// -------------------------------------------------------------------------
	// Data provider
	// -------------------------------------------------------------------------

	/**
	 * Provides one entry per DB type that is currently configured.
	 * The 'svcgen' tag ensures the SQLite file path is unique to this
	 * test class and does not collide with other DB test classes.
	 *
	 * @return array<string, array{DbTestConfig}>
	 */
	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('svcgen');
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns the project for the given RDBMS.
	 * Fails the test if the project was not yet initialized (i.e. testGenerateExitsCleanly
	 * did not run / failed before storing the project).
	 */
	private static function getProject(DbTestConfig $config): OZTestProject
	{
		$rdbms = $config->rdbms;

		if (!isset(self::$projects[$rdbms])) {
			self::fail(\sprintf(
				'Project for %s is not initialized. Did testGenerateExitsCleanly pass?',
				$rdbms,
			));
		}

		return self::$projects[$rdbms];
	}
}
