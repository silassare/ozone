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

namespace OZONE\Tests\Integration\Project;

use OZONE\Core\Scopes\StateLayout;
use OZONE\Tests\Integration\Support\OZTestProject;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the state layout under `data/` and the symlinks the web roots follow to reach the public
 * files: that `oz project create` and `oz scopes add` produce them, and that `oz project link`
 * recreates them after a clone or a deploy that leaves a new release directory.
 *
 * @internal
 *
 * @coversNothing
 */
final class ProjectLinkTest extends TestCase
{
	private const SCOPE = 'myweb';

	private static OZTestProject $proj;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		self::$proj = OZTestProject::create('project-link-test');
		self::$proj->oz('scopes', 'add', '--name=' . self::SCOPE, '--origin=http://web.example.com', '--api=false')
			->mustRun();
	}

	public static function tearDownAfterClass(): void
	{
		self::$proj->destroy();
		parent::tearDownAfterClass();
	}

	/**
	 * @dataProvider provideScopes
	 */
	public function testCreateAndScopesAddProduceEveryStateDirectory(string $scope): void
	{
		foreach (StateLayout::kinds() as $kind) {
			self::assertDirectoryExists(self::path('data/' . $kind . '/' . $scope), $kind);
		}
	}

	public function testTheStateDirectoriesAreTheOnlyThingUnderData(): void
	{
		$entries = \array_values(\array_diff(\scandir(self::path('data')) ?: [], ['.', '..']));

		\sort($entries);

		$kinds = StateLayout::kinds();

		\sort($kinds);

		self::assertSame($kinds, $entries, 'Nothing but the state kinds belongs at the top of data/.');
	}

	/**
	 * @dataProvider provideScopes
	 */
	public function testThePublicLinkPointsAtTheScopePool(string $scope): void
	{
		$link = self::linkPath($scope);

		self::assertTrue(\is_link($link), $link . ' is not a symlink');
		self::assertSame(
			\realpath(self::path('data/static/' . $scope)),
			\realpath((string) \readlink($link))
		);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function provideScopes(): iterable
	{
		return [
			'root scope' => ['root'],
			'sub scope'  => [self::SCOPE],
		];
	}

	public function testLinkRecreatesWhatACloneDoesNotCarry(): void
	{
		// A clone has the sources but neither the links (not version-controlled) nor data/static.
		foreach (self::provideScopes() as [$scope]) {
			\unlink(self::linkPath($scope));
		}

		self::$proj->oz('project', 'link')->mustRun();

		foreach (self::provideScopes() as [$scope]) {
			self::assertTrue(\is_link(self::linkPath($scope)), $scope . ': link not recreated');
		}
	}

	public function testLinkIsIdempotent(): void
	{
		$before = \array_map(
			static fn (array $row): string => (string) \readlink(self::linkPath($row[0])),
			\array_values(self::provideScopes())
		);

		$proc = self::$proj->oz('project', 'link');
		$proc->mustRun();

		$after = \array_map(
			static fn (array $row): string => (string) \readlink(self::linkPath($row[0])),
			\array_values(self::provideScopes())
		);

		self::assertSame($before, $after);
		self::assertStringContainsString('already linked', $proc->getOutput());
	}

	public function testLinkRefusesToDeleteRealContentInTheWay(): void
	{
		$link = self::linkPath('root');

		\unlink($link);
		\mkdir($link, 0o775, true);
		\file_put_contents($link . '/keep.txt', 'do not delete me');

		$proc = self::$proj->oz('project', 'link');
		$proc->run();

		self::assertFileExists($link . '/keep.txt', 'Existing content must never be removed.');

		// Kli word-wraps its output, so the phrase is matched on a whitespace-collapsed copy.
		$output = (string) \preg_replace('~\s+~', ' ', $proc->getOutput() . $proc->getErrorOutput());

		self::assertStringContainsString('is not a symlink', $output);

		// Put it back, so the remaining tests see a linked project.
		\unlink($link . '/keep.txt');
		\rmdir($link);
		self::$proj->oz('project', 'link')->mustRun();
	}

	public function testAFileWrittenInThePoolIsReachableThroughTheLink(): void
	{
		$name = 'probe-' . \bin2hex(\random_bytes(4)) . '.txt';

		\file_put_contents(self::path('data/static/' . self::SCOPE) . \DIRECTORY_SEPARATOR . $name, 'served');

		self::assertSame('served', \file_get_contents(self::linkPath(self::SCOPE) . \DIRECTORY_SEPARATOR . $name));
	}

	/**
	 * The document root of a scope: `public/` for the root scope, `public/{scope}/` otherwise.
	 */
	private static function linkPath(string $scope): string
	{
		$public = 'root' === $scope ? 'public' : 'public/' . $scope;

		return self::path($public . '/' . StateLayout::PUBLIC_LINK);
	}

	private static function path(string $relative): string
	{
		return self::$proj->getPath() . \DIRECTORY_SEPARATOR . \str_replace('/', \DIRECTORY_SEPARATOR, $relative);
	}
}
