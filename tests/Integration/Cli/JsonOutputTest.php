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
 * `--json` on the commands a tool drives: stdout holds one JSON object, `{"ok": ...}`, errors included.
 *
 * @internal
 *
 * @coversNothing
 */
final class JsonOutputTest extends TestCase
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
	public function testDbBuildAndMigrationsCheck(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('json-output-' . $rdbms, shared: false, fresh: true);

		self::$projects[$rdbms] = $proj;
		self::$dbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;

		$proj->writeEnv($config->toEnvArray());

		$build      = self::json($proj, 'db', 'build', '--build-all', '--class-only', '--json');
		$namespaces = \array_column($build['namespaces'], 'namespace');

		self::assertTrue($build['ok']);
		self::assertFalse($build['migration']);
		self::assertContains('OZONE\Core\Db', $namespaces);

		$proj->cleanDb();

		$before = self::json($proj, 'migrations', 'check', '--json');

		self::assertTrue($before['ok']);
		self::assertSame('not_installed', $before['state']);
		self::assertSame([], $before['pending']);

		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();

		$created = self::json($proj, 'migrations', 'check', '--json');

		self::assertSame('initial', $created['pending'][0]['label'] ?? null);
		self::assertSame($created['source_version'], $created['pending'][0]['version']);

		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		$after = self::json($proj, 'migrations', 'check', '--json');

		self::assertSame('installed', $after['state']);
		self::assertSame($after['source_version'], $after['db_version']);
		self::assertSame([], $after['pending']);
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testScopesDeployServerAndDoctor(DbTestConfig $config): void
	{
		$proj = self::project($config);

		$scope = self::json($proj, 'scopes', 'add', '--name=web', '--origin=http://www.example.com', '--api=false', '--json');

		self::assertTrue($scope['ok']);
		self::assertSame('web', $scope['scope']);
		self::assertFalse($scope['api']);
		self::assertDirectoryExists($scope['private']);
		self::assertDirectoryExists($scope['public']);

		$missing  = $proj->getPath() . '/no-deploy-root';
		$releases = self::json($proj, 'deploy', 'releases', '--root=' . $missing, '--json');

		self::assertSame(
			['ok' => true, 'root' => $missing, 'deployed' => false, 'current' => null, 'releases' => []],
			$releases
		);

		$manifest = $proj->getPath() . '/no-manifest.json';
		$status   = self::json($proj, 'server', 'status', '--manifest=' . $manifest, '--json');

		self::assertSame(['ok' => true, 'manifest' => $manifest, 'provisioned' => false, 'steps' => []], $status);

		$doctor = self::json($proj, 'doctor', 'check', '--json');

		self::assertTrue($doctor['ok']);
		self::assertNotEmpty($doctor['checks']);
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testErrorsAreJsonToo(DbTestConfig $config): void
	{
		$proj = self::project($config);

		// an input error Kli reports before the command runs
		$unknown = self::json($proj, 'migrations', 'check', '--json', '--nope');

		self::assertFalse($unknown['ok']);
		self::assertIsString($unknown['error']);

		// an error the command reports while running
		$namespace = self::json($proj, 'db', 'build', '--namespace=Nope\Db', '--json');

		self::assertFalse($namespace['ok']);
		self::assertStringContainsString('Nope\Db', $namespace['error']);

		// an exception no command catches, answered by the global handler: a migration version with no
		// file, so the database cannot initialize
		$proj->oz('settings', 'set', '--group=oz.db.migrations', '--key=OZ_MIGRATION_VERSION', '--value=9999')
			->mustRun();

		try {
			$uncaught = self::json($proj, 'db', 'build', '--class-only', '--json');

			self::assertFalse($uncaught['ok']);
			self::assertNotSame('', $uncaught['error']);
		} finally {
			$proj->oz('settings', 'unset', '--group=oz.db.migrations', '--key=OZ_MIGRATION_VERSION')->mustRun();
		}
	}

	/**
	 * @return iterable<string, array{DbTestConfig}>
	 */
	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('json-output');
	}

	public function testProjectCreate(): void
	{
		$dir = \sys_get_temp_dir() . '/oz_json_create_' . \bin2hex(\random_bytes(4));

		\mkdir($dir, 0o775, true);

		try {
			$proc = new Process([
				\PHP_BINARY,
				\dirname(__DIR__, 3) . '/bin/oz',
				'project',
				'create',
				'--root-dir=' . $dir,
				'--name=json-probe',
				'--namespace=JsonProbe',
				'--class-name=JsonProbeApp',
				'--prefix=JP',
				'--json',
			], $dir);

			$proc->run();

			$out = self::decode($proc);

			self::assertSame(0, $proc->getExitCode());
			self::assertTrue($out['ok']);
			self::assertSame('json-probe', $out['name']);
			self::assertSame('JsonProbe', $out['namespace']);
			self::assertSame(['composer update'], $out['next']);
			self::assertFileExists($dir . '/app/boot.php');
			self::assertContains('success', \array_column($out['messages'], 'level'));
		} finally {
			(new Process(['rm', '-rf', $dir]))->run();
		}
	}

	/**
	 * Runs an `oz` command and returns its stdout, which must be one JSON object and nothing else.
	 *
	 * @return array<string, mixed>
	 */
	private static function json(OZTestProject $proj, string ...$args): array
	{
		$proc = $proj->oz(...$args);

		$proc->run();

		return self::decode($proc);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function decode(Process $proc): array
	{
		$stdout = $proc->getOutput();

		self::assertJson($stdout, "stdout:\n" . $stdout . "\nstderr:\n" . $proc->getErrorOutput());

		$out = \json_decode($stdout, true, 512, \JSON_THROW_ON_ERROR);

		self::assertIsArray($out);
		self::assertSame(0 === $proc->getExitCode(), $out['ok'], 'the exit code matches "ok"');

		return $out;
	}

	private static function project(DbTestConfig $config): OZTestProject
	{
		if (!isset(self::$projects[$config->rdbms])) {
			self::fail(\sprintf('Project for %s not initialized.', $config->rdbms));
		}

		return self::$projects[$config->rdbms];
	}
}
