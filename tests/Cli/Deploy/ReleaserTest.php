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

namespace OZONE\Tests\Cli\Deploy;

use OZONE\Core\Cli\Deploy\ReleaseLayout;
use OZONE\Core\Cli\Deploy\Releaser;
use OZONE\Core\Exceptions\RuntimeException;
use PHPUnit\Framework\TestCase;

/**
 * Class ReleaserTest.
 *
 * The order of the steps is the safety property of a deploy, so it is asserted rather than assumed:
 * everything that can fail has to happen before the `current` symlink moves.
 *
 * @internal
 *
 * @covers \OZONE\Core\Cli\Deploy\ReleaseLayout
 * @covers \OZONE\Core\Cli\Deploy\Releaser
 */
final class ReleaserTest extends TestCase
{
	private const ROOT = '/srv/app';
	private const REPO = 'git@example.com:me/app.git';

	public function testEverythingThatCanFailRunsBeforeTheSwap(): void
	{
		$names = self::stepNames(new Releaser(self::ROOT, self::REPO));
		$live  = \array_search('go-live', $names, true);

		self::assertIsInt($live);

		foreach (['checkout', 'link-shared', 'dependencies', 'link-public', 'orm', 'migrations', 'build'] as $before) {
			$at = \array_search($before, $names, true);

			self::assertIsInt($at, $before . ' is missing');
			self::assertLessThan($live, $at, $before . ' must run before the swap');
		}
	}

	public function testTheBuildComesAfterTheMigrations(): void
	{
		$names = self::stepNames(new Releaser(self::ROOT, self::REPO));

		// The migrations change a setting the route tables are keyed by.
		self::assertGreaterThan(
			(int) \array_search('migrations', $names, true),
			(int) \array_search('build', $names, true)
		);
	}

	public function testTheHealthCheckAndTheRestartComeAfterTheSwap(): void
	{
		$names = self::stepNames(new Releaser(
			self::ROOT,
			self::REPO,
			health_url: 'https://example.com/',
			restart: ['app-worker@1']
		));

		$live = (int) \array_search('go-live', $names, true);

		self::assertGreaterThan($live, (int) \array_search('restart', $names, true));
		self::assertGreaterThan($live, (int) \array_search('health', $names, true));
	}

	public function testTheSwapIsAtomic(): void
	{
		$commands = self::commandsOf(new Releaser(self::ROOT, self::REPO), 'go-live');

		// `ln -sfn` onto an existing symlink is not atomic everywhere: a temporary link then a
		// rename is, so a request never sees a missing `current`.
		self::assertCount(2, $commands);
		self::assertStringContainsString('current.new', $commands[0]);
		self::assertStringStartsWith('mv -T ', $commands[1]);
	}

	public function testTheStateDirectoriesAreLinkedNotHeldByTheRelease(): void
	{
		$commands = \implode(' ', self::commandsOf(new Releaser(self::ROOT, self::REPO), 'link-shared'));

		foreach (ReleaseLayout::sharedDirs() as $dir) {
			self::assertStringContainsString("'/srv/app/" . $dir . "'", $commands, $dir);
		}

		// Otherwise a deploy would orphan the state of the previous release.
		self::assertStringContainsString('ln -s ', $commands);
	}

	public function testTheCheckoutFetchesTheRefSoACommitWorks(): void
	{
		$commands = self::commandsOf(new Releaser(self::ROOT, self::REPO, ref: 'a1b2c3d4'), 'checkout');

		self::assertCount(3, $commands);
		self::assertStringStartsWith('git init --quiet ', $commands[0]);
		// `git clone --branch` refuses a commit: the ref is fetched, whatever it names
		self::assertStringContainsString("fetch --quiet --depth 1 '" . self::REPO . "' 'a1b2c3d4'", $commands[1]);
		self::assertStringEndsWith('checkout --quiet FETCH_HEAD', $commands[2]);
		self::assertStringNotContainsString('--branch', \implode(' ', $commands));
	}

	public function testAnArchiveIsUnpackedInsteadOfCheckedOut(): void
	{
		$commands = self::commandsOf(new Releaser(self::ROOT, '', archive: '/tmp/app.tar.gz', release: 'v1'), 'checkout');

		self::assertSame([
			"mkdir -p '/srv/app/releases/v1'",
			"tar -xzf '/tmp/app.tar.gz' -C '/srv/app/releases/v1'",
		], $commands);
	}

	public function testAReleaseNeedsExactlyOneSource(): void
	{
		foreach ([['', ''], [self::REPO, '/tmp/app.tar.gz']] as [$repository, $archive]) {
			try {
				(new Releaser(self::ROOT, $repository, archive: $archive))->steps();

				self::fail('a release with ' . ('' === $repository ? 'no' : 'two') . ' sources must be refused');
			} catch (RuntimeException $e) {
				self::assertStringContainsString('exactly one source', $e->getMessage());
			}
		}
	}

	public function testMigrationsCanBeSkipped(): void
	{
		self::assertNotContains('migrations', self::stepNames(new Releaser(self::ROOT, self::REPO, migrations: false)));
		self::assertContains('migrations', self::stepNames(new Releaser(self::ROOT, self::REPO)));
	}

	public function testNoRestartOrHealthStepWithoutOptions(): void
	{
		$names = self::stepNames(new Releaser(self::ROOT, self::REPO));

		self::assertNotContains('restart', $names);
		self::assertNotContains('health', $names);
	}

	public function testPruneNeverRemovesTheLiveRelease(): void
	{
		$prune = self::commandsOf(new Releaser(self::ROOT, self::REPO, keep: 3), 'prune')[0];

		self::assertStringContainsString('head -n -3', $prune);
		self::assertStringContainsString('readlink -f', $prune, 'the live release has to be excluded');
		self::assertStringContainsString("'/srv/app/current'", $prune);
	}

	public function testTheReleaseNameIsSortableAndOverridable(): void
	{
		self::assertMatchesRegularExpression('~^\d{14}$~', (new Releaser(self::ROOT, self::REPO))->releaseName());
		self::assertSame('v1', (new Releaser(self::ROOT, self::REPO, release: 'v1'))->releaseName());
	}

	public function testRollbackToAnUnknownReleaseIsRefused(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('~No such release~');

		(new Releaser(self::ROOT, self::REPO))->rollbackSteps('nope');
	}

	public function testReleaseLayoutReadsADeployRoot(): void
	{
		$root = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'oz_release_' . \bin2hex(\random_bytes(6));

		try {
			self::assertFalse(ReleaseLayout::isDeployRoot($root));

			foreach (['20260101000000', '20260102000000', '20260103000000'] as $name) {
				\mkdir(ReleaseLayout::releaseDir($root, $name), 0o775, true);
			}

			self::assertTrue(ReleaseLayout::isDeployRoot($root));
			self::assertSame(
				['20260101000000', '20260102000000', '20260103000000'],
				ReleaseLayout::releases($root)
			);
			self::assertNull(ReleaseLayout::currentRelease($root), 'nothing is live yet');
			self::assertNull(ReleaseLayout::previousRelease($root));

			\symlink(ReleaseLayout::releaseDir($root, '20260103000000'), ReleaseLayout::currentLink($root));

			self::assertSame('20260103000000', ReleaseLayout::currentRelease($root));
			self::assertSame('20260102000000', ReleaseLayout::previousRelease($root));
		} finally {
			@\unlink(ReleaseLayout::currentLink($root));

			foreach (ReleaseLayout::releases($root) as $name) {
				@\rmdir(ReleaseLayout::releaseDir($root, $name));
			}

			@\rmdir(ReleaseLayout::releasesDir($root));
			@\rmdir($root);
		}
	}

	public function testTheOldestReleaseHasNoPrevious(): void
	{
		$root = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'oz_release_' . \bin2hex(\random_bytes(6));

		try {
			\mkdir(ReleaseLayout::releaseDir($root, '20260101000000'), 0o775, true);
			\symlink(ReleaseLayout::releaseDir($root, '20260101000000'), ReleaseLayout::currentLink($root));

			// Rolling back from the first release has nowhere to go, and must not pick itself.
			self::assertNull(ReleaseLayout::previousRelease($root));
		} finally {
			@\unlink(ReleaseLayout::currentLink($root));
			@\rmdir(ReleaseLayout::releaseDir($root, '20260101000000'));
			@\rmdir(ReleaseLayout::releasesDir($root));
			@\rmdir($root);
		}
	}

	/**
	 * @return list<string>
	 */
	private static function stepNames(Releaser $releaser): array
	{
		return \array_map(static fn ($step): string => $step->name, $releaser->steps());
	}

	/**
	 * @return list<string>
	 */
	private static function commandsOf(Releaser $releaser, string $name): array
	{
		foreach ($releaser->steps() as $step) {
			if ($step->name === $name) {
				return $step->commands;
			}
		}

		self::fail(\sprintf('No step "%s".', $name));
	}
}
