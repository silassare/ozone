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

namespace OZONE\Tests\Integration\Cli;

use OZONE\Core\Testing\DbTestConfig;
use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Verifies `oz doctor check` in a real project: it reports the environment and the project, and
 * fails only when something is actually wrong.
 *
 * @internal
 *
 * @coversNothing
 */
final class DoctorCmdTest extends TestCase
{
	/** @var array<string, OZTestProject> */
	private static array $projects = [];

	/** @var array<string, null|string> */
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
	 * @dataProvider provideDbConfig
	 */
	public function testDoctorPassesOnAFreshMigratedProject(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('doctor-' . $rdbms, shared: false);
		$proj->writeEnv($config->toEnvArray());

		self::$projects[$rdbms] = $proj;
		self::$dbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		// Drop all tables so FK constraint names don't collide with another test class's project:
		// table names are kept apart by each project's random prefix, but MySQL scopes foreign key
		// constraint names to the *schema*, and every class shares one database.
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		$proc = $proj->oz('doctor', 'check', '--json');
		$proc->run();

		self::assertJson($proc->getOutput(), self::outputOf($proc));

		$out = \json_decode($proc->getOutput(), true, 512, \JSON_THROW_ON_ERROR);

		self::assertIsArray($out);
		self::assertSame(0, $out['error'], 'doctor reported a failure: ' . $proc->getOutput());
		self::assertSame(0, $proc->getExitCode());

		$by_name = \array_column($out['data']['checks'], 'status', 'name');

		self::assertSame('ok', $by_name['PHP version']);
		self::assertSame('ok', $by_name['ext-pdo']);
		self::assertSame('ok', $by_name['OZ_APP_SALT']);
		self::assertSame('ok', $by_name['OZ_APP_SECRET']);
		self::assertSame('ok', $by_name['data/ writable']);
		self::assertSame('ok', $by_name['Database']);
		self::assertSame('ok', $by_name['Migrations']);
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testDoctorFailsOnPendingMigrations(DbTestConfig $config): void
	{
		$proj = self::getProject($config);

		// A second migration, created but not run: the source code is ahead of the database.
		// The version must have a migration file, or the database cannot even initialize, so this
		// goes through `migrations create` rather than writing the setting directly.
		$proj->oz('migrations', 'create', '--force', '--label=probe')->mustRun();

		$proc = $proj->oz('doctor', 'check', '--json');
		$proc->run();

		self::assertJson($proc->getOutput(), self::outputOf($proc));

		$out     = \json_decode($proc->getOutput(), true, 512, \JSON_THROW_ON_ERROR);
		$by_name = \array_column($out['data']['checks'], 'status', 'name');

		self::assertSame(1, $out['error']);
		self::assertSame(1, $proc->getExitCode());
		self::assertSame('fail', $by_name['Migrations']);
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testDoctorReportsASchemaThatCannotBePrepared(DbTestConfig $config): void
	{
		$proj = self::getProject($config);

		// A migration version with no file: Db::init() cannot prepare the schema. Every oz command
		// used to die at bootstrap with "Unable to initialize database.", doctor included.
		$proj->oz(
			'settings',
			'set',
			'--group=oz.db.migrations',
			'--key=OZ_MIGRATION_VERSION',
			'--value=9999'
		)->mustRun();

		try {
			$proc = $proj->oz('doctor', 'check', '--json');
			$proc->run();

			self::assertJson($proc->getOutput(), self::outputOf($proc));

			$out     = \json_decode($proc->getOutput(), true, 512, \JSON_THROW_ON_ERROR);
			$by_name = \array_column($out['data']['checks'], 'status', 'name');

			self::assertSame(1, $out['error']);
			self::assertSame(1, $proc->getExitCode());
			self::assertSame('fail', $by_name['Database schema'], self::outputOf($proc));

			// The environment checks still ran, so the report is not reduced to the failure.
			self::assertSame('ok', $by_name['PHP version']);

			// A command that needs the database still fails: the failure is recorded, not swallowed.
			// (`oz migrations check` is not one -- it reads the version silently and only reports.)
			$needs_db = $proj->oz('migrations', 'run', '--skip-backup');
			$needs_db->run();

			self::assertNotSame(0, $needs_db->getExitCode(), self::outputOf($needs_db));
			self::assertStringContainsString(
				'database',
				\strtolower($needs_db->getOutput() . $needs_db->getErrorOutput())
			);
		} finally {
			$proj->oz('settings', 'unset', '--group=oz.db.migrations', '--key=OZ_MIGRATION_VERSION')
				->mustRun();
		}
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testDoctorWarnsOutsideAProject(DbTestConfig $config): void
	{
		$proj = self::getProject($config);

		// Run from a directory that is not a project: only the environment checks apply.
		$proc = $proj->ozIn(\sys_get_temp_dir(), 'doctor', 'check', '--json');
		$proc->run();

		self::assertJson($proc->getOutput(), self::outputOf($proc));

		$out     = \json_decode($proc->getOutput(), true, 512, \JSON_THROW_ON_ERROR);
		$by_name = \array_column($out['data']['checks'], 'status', 'name');

		self::assertSame(0, $out['error'], 'A machine without a project is not a failure.');
		self::assertSame('warn', $by_name['Project']);
		self::assertArrayNotHasKey('Database', $by_name);
	}

	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('doctor');
	}

	private static function outputOf(Process $proc): string
	{
		return \sprintf("stdout:\n%s\nstderr:\n%s", $proc->getOutput(), $proc->getErrorOutput());
	}

	private static function getProject(DbTestConfig $config): OZTestProject
	{
		if (!isset(self::$projects[$config->rdbms])) {
			self::fail(\sprintf('Project for %s not initialized.', $config->rdbms));
		}

		return self::$projects[$config->rdbms];
	}
}
