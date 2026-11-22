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

namespace OZONE\Tests\Integration\Support;

use Override;
use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The test kit answering for itself: a project reused from an earlier run runs on OZone's current
 * dependencies and never on the ones it was created with, and a server that never came up is
 * reported with the reason rather than with the length of a wait.
 *
 * Every class that serves a project reports through `startServer()`, so a failure of its own is
 * read fifteen times over.
 *
 * @internal
 */
#[CoversNothing]
final class OZTestProjectTest extends TestCase
{
	private const string NAME = 'harness-reuse';

	#[Override]
	public static function tearDownAfterClass(): void
	{
		OZTestProject::create(self::NAME)->destroy();

		parent::tearDownAfterClass();
	}

	public function testAReusedProjectFollowsTheCurrentDependencySet(): void
	{
		$vendor  = OZTestProject::create(self::NAME)->getPath() . '/vendor';
		$current = \readlink($vendor);

		self::assertIsString($current);
		self::assertDirectoryExists($current);

		// As a project created before a composer update in OZone has it: another set's vendor/.
		$stale = \dirname($vendor) . '/stale-vendor';

		\mkdir($stale);
		\unlink($vendor);
		\symlink($stale, $vendor);

		OZTestProject::create(self::NAME);

		self::assertSame($current, \readlink($vendor));

		// And as a killed install leaves it: a vendor/ that is no symlink.
		\unlink($vendor);
		\mkdir($vendor . '/composer', 0o775, true);

		OZTestProject::create(self::NAME);

		self::assertSame($current, \readlink($vendor));
		self::assertFileExists($vendor . '/autoload.php');
	}

	/**
	 * A key set over a group leaves the project's own file of the group running in the project: a
	 * generated `oz.db.php` reads the database from the project's `.env`, and once read in the test's
	 * process and written back, it named `mysql` on `localhost` for every command after.
	 */
	public function testASettingSetOverAGroupLeavesTheProjectFileReadingItsEnvironment(): void
	{
		$proj = OZTestProject::create('harness-settings', fresh: true);

		try {
			$proj->writeEnv([
				'OZ_DB_RDBMS' => 'sqlite',
				'OZ_DB_HOST'  => $proj->getPath() . '/harness_settings.sqlite',
			]);

			$file  = $proj->getPath() . '/app/settings/oz.db.php';
			$class = $proj->getPath() . '/app/Db/Base/HarnessNoteBase.php';

			self::assertStringContainsString("env('OZ_DB_RDBMS'", (string) \file_get_contents($file));

			// Two keys of the group, one after the other, and one of another group.
			$proj->setSetting('oz.db', 'OZ_DB_GENERATED_TIMESTAMP', false);
			$proj->setSetting('oz.db', 'OZ_DB_CHARSET', 'utf8mb4');
			$proj->setSetting('oz.db.schema', 'harness_notes', [
				'plural_name'   => 'harness_notes',
				'singular_name' => 'harness_note',
				'column_prefix' => 'hn',
				'columns'       => [
					'id'    => ['type' => 'bigint', 'auto_increment' => true, 'unsigned' => true],
					'title' => ['type' => 'string', 'max' => 60],
				],
				'constraints'   => [['type' => 'primary_key', 'columns' => ['id']]],
			]);

			// The project reaches its own database: migrations connect to it and run.
			$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
			$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
			$proj->oz('migrations', 'run', '--skip-backup')->mustRun();
			$proj->oz('db', 'build', '--class-only')->mustRun();

			// And the first key held through the second: the header carries no generation time.
			self::assertFileExists($class);
			self::assertStringNotContainsString(' * Time: ', (string) \file_get_contents($class));

			// A group file written since by anything else is the project's file from then on: what was
			// set before is dropped, as a rewrite always dropped it, and a new key is set over it.
			$proj->writeFile('app/settings/oz.db.php', (string) \file_get_contents($file . '.base'));
			$proj->setSetting('oz.db', 'OZ_DB_CHARSET', 'utf8mb4');
			$proj->oz('db', 'build', '--class-only')->mustRun();

			self::assertStringContainsString(' * Time: ', (string) \file_get_contents($class));
		} finally {
			$proj->destroy();
		}
	}

	/**
	 * A test's server listens on a port the system handed out, never the first free one of the list
	 * `oz project serve` falls back on: two processes starting a server at the same moment both
	 * found 8080 free, and one test then read the other's pages.
	 */
	public function testAServerIsStartedOnAPortTheSystemHandsOut(): void
	{
		$proj = OZTestProject::create('harness-port', fresh: true);

		try {
			$proj->writeEnv([
				'OZ_DB_RDBMS' => 'sqlite',
				'OZ_DB_HOST'  => $proj->getPath() . '/harness_port.sqlite',
			]);

			[$server, $host, $port] = $proj->startServer();

			self::assertNotContains($port, [8080, 8888, 9000, 2227], 'serve\'s own fallback list');
			self::assertTrue($server->isRunning());

			$socket = @\fsockopen($host, $port, $errno, $errstr, 1.0);

			self::assertIsResource($socket, $errstr);
			\fclose($socket);
		} finally {
			$proj->destroy();
		}
	}

	public function testAServeThatExitedIsReportedWithWhatItPrinted(): void
	{
		$proj = OZTestProject::create('harness-server', fresh: true);

		$proj->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $proj->getPath() . '/harness_server.sqlite',
		]);

		$started = \microtime(true);

		try {
			// A scope the project does not have: `oz project serve` says so and exits, before it
			// ever writes server.json.
			$proj->startServer('no-such-scope');

			self::fail('A scope the project does not have cannot be served.');
		} catch (RuntimeException $e) {
			$elapsed = \microtime(true) - $started;
			$message = $e->getMessage();

			self::assertStringContainsString('(scope=no-such-scope)', $message);
			self::assertStringContainsString('exited before it wrote server.json', $message);

			// What the process printed is the failure itself, not a footnote: the wait said only
			// its own length, and a port already taken read the same as a project that cannot boot.
			self::assertStringNotContainsString('It printed nothing.', $message);
			self::assertStringContainsString('no-such-scope" not found', $message);

			// And nothing was waited out: the process was gone, so the deadline was never reached.
			self::assertLessThan(OZTestProject::SERVER_JSON_WAIT, $elapsed);
		} finally {
			$proj->destroy();
		}
	}
}
