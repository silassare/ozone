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
