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

use OZONE\Core\App\InstanceLayout;
use OZONE\Core\Cli\Deploy\ReleaseLayout;
use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Deploys for real: a local git repository is released into a deploy root, the `current` symlink is
 * swapped, a second release is deployed over it, and a rollback puts the first one back.
 *
 * The project is a real OZone project, but `composer install` and the migrations are skipped
 * (`--skip-migrations`, and a `vendor/` the checkout already carries) so the test exercises the
 * release mechanics -- the layout, the shared state, the atomic swap, the pruning and the rollback
 * -- without a network or a database.
 *
 * @internal
 *
 * @coversNothing
 */
final class DeployRunTest extends TestCase
{
	private static OZTestProject $proj;

	private static string $root  = '';
	private static string $repo  = '';

	/**
	 * Each release's build directory, taken while that release is on disk: the key is its real path,
	 * which a release that has been pruned no longer has.
	 *
	 * @var array<string, string>
	 */
	private static array $build_dirs = [];

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		self::$proj = OZTestProject::create('deploy-run-test');

		$base       = \dirname(self::$proj->getPath());
		self::$repo = $base . \DIRECTORY_SEPARATOR . 'deploy-run-repo.git';
		self::$root = $base . \DIRECTORY_SEPARATOR . 'deploy-run-root';

		self::rmdir(self::$repo);
		self::rmdir(self::$root);

		// A real repository, so the checkout (init, fetch, checkout) is exercised as it is in production.
		// The fixture owns it: a leftover .git from an interrupted run would make the commit below
		// a no-op and fail with "nothing to commit".
		$project = self::$proj->getPath();

		self::rmdir($project . \DIRECTORY_SEPARATOR . '.git');
		self::git(['init', '--quiet', '--initial-branch=main'], $project);
		self::git(['config', 'user.email', 'test@example.com'], $project);
		self::git(['config', 'user.name', 'Test'], $project);
		self::git(['add', '-A'], $project);
		self::git(['commit', '--quiet', '-m', 'first'], $project);
		self::git(['clone', '--quiet', '--bare', $project, self::$repo], $base);

		// `.env` is not in the repository (it holds the app secret and the database password), so it
		// lives in the deploy root and every release is linked to it -- which is what an operator
		// does once, by hand, before the first deploy.
		\mkdir(self::$root, 0o775, true);
		\copy($project . \DIRECTORY_SEPARATOR . '.env', self::$root . \DIRECTORY_SEPARATOR . '.env');
	}

	public static function tearDownAfterClass(): void
	{
		self::rmdir(self::$root);
		self::rmdir(self::$repo);
		self::$proj->destroy();

		parent::tearDownAfterClass();
	}

	public function testDryRunTouchesNothing(): void
	{
		$proc = self::deploy(['--dry-run']);
		$proc->mustRun();

		self::assertStringContainsString('go-live', $proc->getOutput());
		self::assertDirectoryDoesNotExist(ReleaseLayout::releasesDir(self::$root));
	}

	public function testADeployRootWithoutTheSharedEnvIsRefused(): void
	{
		$env = self::$root . \DIRECTORY_SEPARATOR . '.env';

		\rename($env, $env . '.away');

		try {
			$proc = self::deploy(['--release=no-env']);
			$proc->run();

			$output = (string) \preg_replace('~\s+~', ' ', $proc->getOutput() . $proc->getErrorOutput());

			self::assertNotSame(0, $proc->getExitCode());
			self::assertStringContainsString('.env', $output);
			self::assertDirectoryDoesNotExist(
				ReleaseLayout::releaseDir(self::$root, 'no-env'),
				'nothing must be created when the root is not ready'
			);
		} finally {
			\rename($env . '.away', $env);
		}
	}

	public function testFirstReleaseGoesLive(): void
	{
		$proc = self::deploy(['--release=r1']);
		$proc->mustRun();

		self::assertDirectoryExists(ReleaseLayout::releaseDir(self::$root, 'r1'));
		self::assertSame('r1', ReleaseLayout::currentRelease(self::$root));

		// The state and the secrets live beside the releases; the release only links to them.
		foreach (\array_merge(ReleaseLayout::sharedDirs(), ReleaseLayout::sharedFiles()) as $dir) {
			self::assertFileExists(self::$root . \DIRECTORY_SEPARATOR . $dir);
			self::assertTrue(
				\is_link(ReleaseLayout::releaseDir(self::$root, 'r1') . \DIRECTORY_SEPARATOR . $dir),
				$dir . ' must be a link inside the release'
			);
		}

		// `oz project link` ran inside the release, so the public files are reachable.
		$link = ReleaseLayout::releaseDir(self::$root, 'r1') . \DIRECTORY_SEPARATOR . 'public'
			. \DIRECTORY_SEPARATOR . 'static';

		self::assertTrue(\is_link($link), 'public/static must be linked in the release');
		self::assertSame(
			\realpath(self::$root . \DIRECTORY_SEPARATOR . 'data' . \DIRECTORY_SEPARATOR . 'root'
				. \DIRECTORY_SEPARATOR . 'static'),
			\realpath((string) \readlink($link)),
			'it must resolve into the shared data directory, not into the release'
		);
	}

	public function testAFileWrittenByOneReleaseIsSeenByTheNext(): void
	{
		// The point of sharing data/: an upload does not belong to the release that received it.
		$uploads = self::$root . \DIRECTORY_SEPARATOR . 'data' . \DIRECTORY_SEPARATOR . 'root'
			. \DIRECTORY_SEPARATOR . 'static';

		\file_put_contents($uploads . \DIRECTORY_SEPARATOR . 'kept.txt', 'survives a deploy');

		self::deploy(['--release=r2'])->mustRun();

		self::assertSame('r2', ReleaseLayout::currentRelease(self::$root));

		$through_new_release = ReleaseLayout::releaseDir(self::$root, 'r2') . \DIRECTORY_SEPARATOR
			. 'public' . \DIRECTORY_SEPARATOR . 'static' . \DIRECTORY_SEPARATOR . 'kept.txt';

		self::assertSame('survives a deploy', \file_get_contents($through_new_release));
	}

	public function testEachReleaseGeneratesIntoItsOwnBuildDirectory(): void
	{
		$r1 = ReleaseLayout::releaseDir(self::$root, 'r1');
		$r2 = ReleaseLayout::releaseDir(self::$root, 'r2');

		$build = self::$root . \DIRECTORY_SEPARATOR . InstanceLayout::DIR . \DIRECTORY_SEPARATOR
			. InstanceLayout::BUILD . \DIRECTORY_SEPARATOR;

		// The shape of a generated class comes from the Gobl and code generator in the release's own
		// vendor/, so two releases on disk at once cannot share one directory for them.
		self::assertNotSame(InstanceLayout::releaseKey($r1), InstanceLayout::releaseKey($r2));

		foreach (['r1' => $r1, 'r2' => $r2] as $name => $release) {
			$dir = $build . InstanceLayout::releaseKey($release);

			self::$build_dirs[$name] = $dir;

			self::assertDirectoryExists($dir, $name . ' must have a build directory of its own');
			self::assertDirectoryExists(
				$dir . \DIRECTORY_SEPARATOR . InstanceLayout::BUILD_PLUGINS . \DIRECTORY_SEPARATOR
					. 'OZONE' . \DIRECTORY_SEPARATOR . 'Core' . \DIRECTORY_SEPARATOR . 'Db',
				$name . ' must hold the ORM classes its own deploy generated'
			);
		}
	}

	public function testAGeneratedClassLeftByAnOlderLayoutDoesNotStopACommand(): void
	{
		// What a deploy root carries after an OZone that kept the generated classes in one shared
		// directory: a class whose shape is that of the release that generated it. Loading this one
		// is a fatal error, not an exception -- PHP refuses a constant redeclared without the type
		// its parent now gives it -- so nothing could catch it and every `oz` command died with it,
		// including the one command that regenerates the classes.
		$stale = self::$root . \DIRECTORY_SEPARATOR . InstanceLayout::DIR . \DIRECTORY_SEPARATOR
			. InstanceLayout::BUILD . \DIRECTORY_SEPARATOR . InstanceLayout::BUILD_PLUGINS
			. \DIRECTORY_SEPARATOR . 'OZONE' . \DIRECTORY_SEPARATOR . 'Core' . \DIRECTORY_SEPARATOR
			. 'Db' . \DIRECTORY_SEPARATOR . 'Base';

		\mkdir($stale, 0o775, true);
		\file_put_contents(
			$stale . \DIRECTORY_SEPARATOR . 'OZSessionsQueryBase.php',
			"<?php\n\nnamespace OZONE\\Core\\Db\\Base;\n\n"
				. "class OZSessionsQueryBase extends \\Gobl\\ORM\\ORMTableQuery\n{\n"
				. "\tprotected const FILTER_METHODS = [];\n}\n"
		);

		$release = ReleaseLayout::releaseDir(self::$root, (string) ReleaseLayout::currentRelease(self::$root));
		$proc    = self::ozIn($release, ['db', 'build', '--build-all', '--class-only']);
		$proc->run();

		self::assertSame(
			0,
			$proc->getExitCode(),
			'the stale class is in no release\'s directory, so nothing loads it: '
				. $proc->getOutput() . $proc->getErrorOutput()
		);

		// And it is left where it is: a release still running the older OZone reads it.
		self::assertFileExists($stale . \DIRECTORY_SEPARATOR . 'OZSessionsQueryBase.php');
	}

	public function testRollbackPutsThePreviousReleaseBack(): void
	{
		self::assertSame('r2', ReleaseLayout::currentRelease(self::$root));
		self::assertSame('r1', ReleaseLayout::previousRelease(self::$root));

		$proc = self::oz(['deploy', 'rollback', '--root=' . self::$root]);
		$proc->mustRun();

		self::assertSame('r1', ReleaseLayout::currentRelease(self::$root));
		self::assertStringContainsString('does not undo a migration', $proc->getOutput());

		// Both releases are still there: a rollback moves a symlink, it does not delete anything.
		self::assertDirectoryExists(ReleaseLayout::releaseDir(self::$root, 'r2'));
	}

	public function testReleasesAreListedWithTheLiveOneMarked(): void
	{
		$proc = self::oz(['deploy', 'releases', '--root=' . self::$root]);
		$proc->mustRun();

		$output = $proc->getOutput();

		self::assertStringContainsString('r1', $output);
		self::assertStringContainsString('r2', $output);
		self::assertMatchesRegularExpression('~\*\s+r1~', $output, 'the live release is marked');
	}

	public function testPruningKeepsTheNewestReleasesAndTheLiveOne(): void
	{
		// Pruning runs last, so `current` already points at the new release by then: with --keep=1
		// the new one is both the newest and the live one, and the older ones go.
		self::deploy(['--release=r3', '--keep=1'])->mustRun();

		self::assertSame('r3', ReleaseLayout::currentRelease(self::$root));
		self::assertDirectoryExists(ReleaseLayout::releaseDir(self::$root, 'r3'));
		self::assertSame(['r3'], ReleaseLayout::releases(self::$root));

		// And what `current` resolves to is still there, which is the property that matters: the
		// command excludes it explicitly, so a live release is never removed even if the count says
		// it should be (asserted on the command itself in ReleaserTest).
		self::assertDirectoryExists(
			(string) \realpath(ReleaseLayout::currentLink(self::$root)),
			'the live release must survive its own prune'
		);
	}

	public function testTheBuildOutputOfAPrunedReleaseGoesWithTheNextDeploy(): void
	{
		$build = self::$root . \DIRECTORY_SEPARATOR . InstanceLayout::DIR . \DIRECTORY_SEPARATOR
			. InstanceLayout::BUILD . \DIRECTORY_SEPARATOR;

		$dir_of = static fn (string $release): string => $build . InstanceLayout::releaseKey(
			ReleaseLayout::releaseDir(self::$root, $release)
		);

		// r1 and r2 were pruned by the deploy before this one, and what they compiled is still there:
		// `oz project build` is a step of the release, and it runs before the release that follows it
		// prunes anything, so it only ever sees the releases an earlier deploy removed. One deploy's
		// worth of build output is orphaned at a time, never more.
		self::assertSame(['r3'], ReleaseLayout::releases(self::$root));
		self::assertCount(2, self::$build_dirs, 'the directories of r1 and r2, taken while they existed');

		foreach (self::$build_dirs as $name => $dir) {
			self::assertDirectoryExists($dir, $name . ' was pruned, what it compiled is still here');
		}

		self::deploy(['--release=r3b'])->mustRun();

		foreach (self::$build_dirs as $name => $dir) {
			self::assertDirectoryDoesNotExist(
				$dir,
				$name . ' is gone, and nothing else would ever remove what it compiled'
			);
		}

		self::assertDirectoryExists($dir_of('r3'), 'a release still on disk keeps what it compiled');
		self::assertDirectoryExists($dir_of('r3b'), 'and so does the live one');

		// Not a release directory, so not an orphan: it is what a release still running the older
		// OZone reads, and removing it would stop that release serving.
		self::assertFileExists(
			$build . InstanceLayout::BUILD_PLUGINS . \DIRECTORY_SEPARATOR . 'OZONE'
				. \DIRECTORY_SEPARATOR . 'Core' . \DIRECTORY_SEPARATOR . 'Db' . \DIRECTORY_SEPARATOR
				. 'Base' . \DIRECTORY_SEPARATOR . 'OZSessionsQueryBase.php'
		);
	}

	public function testAFailedHealthCheckRollsTheReleaseBack(): void
	{
		$live = (string) ReleaseLayout::currentRelease(self::$root);

		// A URL nothing answers: the release goes live, fails its check, and is rolled back.
		$proc = self::deploy(['--release=r4', '--health-url=http://127.0.0.1:1/health']);
		$proc->run();

		self::assertNotSame(0, $proc->getExitCode());
		self::assertSame(
			$live,
			ReleaseLayout::currentRelease(self::$root),
			'current must be back on the release that was serving'
		);
	}

	/**
	 * @param list<string> $args
	 */
	private static function deploy(array $args): Process
	{
		return self::oz(\array_merge([
			'deploy',
			'run',
			'--root=' . self::$root,
			'--repository=' . self::$repo,
			'--ref=main',
			'--skip-migrations',
		], $args));
	}

	/**
	 * @param list<string> $args
	 */
	private static function oz(array $args): Process
	{
		return new Process(
			[\PHP_BINARY, \dirname(__DIR__, 3) . '/bin/oz', ...$args],
			self::$proj->getPath(),
			null,
			null,
			600.0
		);
	}

	/**
	 * `oz` run from inside a release, as every step of a deploy runs it.
	 *
	 * @param list<string> $args
	 */
	private static function ozIn(string $release, array $args): Process
	{
		return new Process(
			[\PHP_BINARY, \dirname(__DIR__, 3) . '/bin/oz', ...$args],
			$release,
			null,
			null,
			600.0
		);
	}

	/**
	 * @param list<string> $args
	 */
	private static function git(array $args, string $cwd): void
	{
		(new Process(['git', ...$args], $cwd, null, null, 120.0))->mustRun();
	}

	private static function rmdir(string $dir): void
	{
		if (!\is_dir($dir)) {
			return;
		}

		(new Process(['rm', '-rf', $dir], null, null, null, 120.0))->run();
	}
}
