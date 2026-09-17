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

use OZONE\Core\Cli\Deploy\ReleaseLayout;
use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * `oz deploy run` from each source a release can come from, for real: a commit of a repository, a git
 * bundle (no server holding the repository), and a tar archive (no git at all). Each release goes live
 * with the code of its source. As in DeployRunTest, composer and the migrations are skipped.
 *
 * @internal
 *
 * @coversNothing
 */
final class DeploySourcesTest extends TestCase
{
	private static OZTestProject $proj;

	private static string $base = '';

	private static string $root = '';

	private static string $first_commit = '';

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		self::$proj = OZTestProject::create('deploy-sources-test');

		$project    = self::$proj->getPath();
		self::$base = \dirname($project) . \DIRECTORY_SEPARATOR . 'deploy-sources';
		self::$root = self::$base . \DIRECTORY_SEPARATOR . 'root';

		self::rmdir(self::$base);
		self::rmdir($project . \DIRECTORY_SEPARATOR . '.git');
		\mkdir(self::$root, 0o775, true);

		// Two commits, each with its own marker file content, so a release shows which one it holds.
		self::git(['init', '--quiet', '--initial-branch=main'], $project);
		self::git(['config', 'user.email', 'test@example.com'], $project);
		self::git(['config', 'user.name', 'Test'], $project);
		\file_put_contents($project . '/RELEASE_MARKER', 'first');
		self::git(['add', '-A'], $project);
		self::git(['commit', '--quiet', '-m', 'first'], $project);

		self::$first_commit = \trim(self::git(['rev-parse', 'HEAD'], $project));

		\file_put_contents($project . '/RELEASE_MARKER', 'second');
		self::git(['commit', '--quiet', '-am', 'second'], $project);

		self::git(['clone', '--quiet', '--bare', $project, self::$base . '/repo.git'], self::$base);
		self::git(['bundle', 'create', self::$base . '/repo.bundle', '--all'], $project);
		self::git(['archive', '--format=tar.gz', '-o', self::$base . '/repo.tar.gz', 'HEAD'], $project);

		\copy($project . '/.env', self::$root . '/.env');
	}

	public static function tearDownAfterClass(): void
	{
		self::rmdir(self::$base);
		self::$proj->destroy();

		parent::tearDownAfterClass();
	}

	public function testACommitIsDeployed(): void
	{
		self::deploy(['--repository=' . self::$base . '/repo.git', '--ref=' . self::$first_commit, '--release=commit'])
			->mustRun();

		self::assertLive('commit', 'first');
	}

	public function testABranchOfABundleIsDeployed(): void
	{
		self::deploy(['--repository=' . self::$base . '/repo.bundle', '--ref=main', '--release=bundle'])->mustRun();

		self::assertLive('bundle', 'second');
	}

	public function testAnArchiveIsDeployed(): void
	{
		self::deploy(['--archive=' . self::$base . '/repo.tar.gz', '--release=archive'])->mustRun();

		self::assertLive('archive', 'second');
	}

	public function testExactlyOneSourceIsRequired(): void
	{
		foreach ([[], ['--repository=' . self::$base . '/repo.git', '--archive=' . self::$base . '/repo.tar.gz']] as $args) {
			$proc = self::deploy([...$args, '--release=refused']);

			$proc->run();

			self::assertNotSame(0, $proc->getExitCode());
			self::assertStringContainsString('--repository', $proc->getOutput() . $proc->getErrorOutput());
			self::assertDirectoryDoesNotExist(ReleaseLayout::releaseDir(self::$root, 'refused'));
		}
	}

	private static function assertLive(string $release, string $marker): void
	{
		self::assertSame($release, ReleaseLayout::currentRelease(self::$root));
		self::assertStringEqualsFile(
			ReleaseLayout::releaseDir(self::$root, $release) . '/RELEASE_MARKER',
			$marker
		);
	}

	/**
	 * @param list<string> $args
	 */
	private static function deploy(array $args): Process
	{
		return new Process(
			[
				\PHP_BINARY,
				\dirname(__DIR__, 3) . '/bin/oz',
				'deploy',
				'run',
				'--root=' . self::$root,
				'--skip-migrations',
				...$args,
			],
			self::$proj->getPath(),
			null,
			null,
			600.0
		);
	}

	/**
	 * @param list<string> $args
	 */
	private static function git(array $args, string $cwd): string
	{
		$proc = new Process(['git', ...$args], $cwd, null, null, 120.0);

		$proc->mustRun();

		return $proc->getOutput();
	}

	private static function rmdir(string $dir): void
	{
		if (!\is_dir($dir)) {
			return;
		}

		(new Process(['rm', '-rf', $dir], null, null, null, 120.0))->run();
	}
}
