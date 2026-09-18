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

namespace OZONE\Tests\Integration\Users;

use OZONE\Core\Testing\DbTestConfig;
use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * `oz users grant`, and the doctor's super admin check, in a real project.
 *
 * @internal
 *
 * @coversNothing
 */
final class UsersGrantTest extends TestCase
{
	private const EMAIL = 'ada@example.com';

	/** @var array<string, OZTestProject> */
	private static array $projects = [];

	/** @var array<string, string> */
	private static array $user_ids = [];

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
		self::$user_ids = [];
		self::$dbFiles  = [];

		parent::tearDownAfterClass();
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testDoctorWarnsWhenNoUserIsSuperAdmin(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('users-grant-' . $rdbms, shared: false, fresh: true);

		self::$projects[$rdbms] = $proj;
		self::$dbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;

		$proj->writeEnv($config->toEnvArray());
		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		// MySQL scopes foreign key constraint names to the schema, which every class shares.
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		$proj->writeFileFromStub('SeedUser', 'seed_user.php', ['email' => self::EMAIL]);

		$seed = new Process([\PHP_BINARY, 'seed_user.php'], $proj->getPath());
		$seed->mustRun();

		self::$user_ids[$rdbms] = \trim($seed->getOutput());

		self::assertMatchesRegularExpression('~^\d+$~', self::$user_ids[$rdbms], $seed->getErrorOutput());
		self::assertSame(['warn', 'none'], self::superAdminCheck($proj));
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testGrantsSuperAdminByEmail(DbTestConfig $config): void
	{
		$proj = self::project($config);
		$proc = $proj->oz('users', 'grant', '--user=' . self::EMAIL, '--role=super-admin');

		$proc->run();

		self::assertSame(0, $proc->getExitCode(), $proc->getOutput() . $proc->getErrorOutput());
		self::assertStringContainsString('Role "super-admin" given', $proc->getOutput());
		self::assertSame(['ok', 'present'], self::superAdminCheck($proj));
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testGrantsByIdAndAgainWithoutError(DbTestConfig $config): void
	{
		$proj = self::project($config);
		$id   = self::$user_ids[$config->rdbms];

		foreach ([1, 2] as $attempt) {
			$proc = $proj->oz('users', 'grant', '--user=' . $id, '--role=admin', '--by=id');

			$proc->run();

			self::assertSame(0, $proc->getExitCode(), $attempt . ': ' . $proc->getOutput());
		}
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testRefusesWhatItCannotGrant(DbTestConfig $config): void
	{
		$proj = self::project($config);

		$cases = [
			'unknown role' => [['--user=' . self::EMAIL, '--role=wizard'], 'Unknown role "wizard"'],
			'unknown user' => [['--user=nobody@example.com', '--role=admin'], 'No "user" user with email'],
			'unknown --by' => [['--user=x', '--role=admin', '--by=shoe-size'], '--by must be one of'],
		];

		foreach ($cases as $label => [$args, $message]) {
			$proc = $proj->oz('users', 'grant', ...$args);

			$proc->run();

			self::assertNotSame(0, $proc->getExitCode(), $label);
			self::assertStringContainsString($message, $proc->getOutput() . $proc->getErrorOutput(), $label);
		}
	}

	/**
	 * @return iterable<string, array{DbTestConfig}>
	 */
	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('users-grant');
	}

	/**
	 * The status and detail of the doctor's super admin check.
	 *
	 * @return array{0: string, 1: string}
	 */
	private static function superAdminCheck(OZTestProject $proj): array
	{
		$proc = $proj->oz('doctor', 'check', '--json');
		$proc->run();

		$out     = \json_decode($proc->getOutput(), true, 512, \JSON_THROW_ON_ERROR);
		$by_name = \array_column($out['data']['checks'], null, 'name');

		self::assertArrayHasKey('Super admin', $by_name, $proc->getOutput());

		return [$by_name['Super admin']['status'], $by_name['Super admin']['detail']];
	}

	private static function project(DbTestConfig $config): OZTestProject
	{
		if (!isset(self::$projects[$config->rdbms])) {
			self::fail(\sprintf('Project for %s not initialized.', $config->rdbms));
		}

		return self::$projects[$config->rdbms];
	}
}
