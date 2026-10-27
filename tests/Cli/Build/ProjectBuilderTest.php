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

namespace OZONE\Tests\Cli\Build;

use OZONE\Core\App\InstanceLayout;
use OZONE\Core\Cli\Build\ProjectBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class ProjectBuilderTest.
 *
 * The preload script picks, when PHP starts, the list of the release that is live: run here on real
 * directories and symlinks, laid out as `oz deploy` lays out a deploy root.
 *
 * @internal
 */
#[CoversClass(ProjectBuilder::class)]
final class ProjectBuilderTest extends TestCase
{
	private string $dir;

	protected function setUp(): void
	{
		parent::setUp();

		$this->dir = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'oz-preload-' . \bin2hex(\random_bytes(6));

		\mkdir($this->dir . '/.ozone/build', 0o775, true);

		$this->dir = (string) \realpath($this->dir);

		\file_put_contents($this->dir . '/.ozone/preload.php', "<?php\n\n" . ProjectBuilder::preloadStub());
	}

	protected function tearDown(): void
	{
		self::remove($this->dir);

		parent::tearDown();
	}

	public function testAProjectPreloadsItsOwnList(): void
	{
		$this->writeList($this->dir, 'project');

		self::assertSame('project', $this->preload());
	}

	public function testADeployRootPreloadsTheReleaseCurrentPointsTo(): void
	{
		foreach (['r1', 'r2'] as $release) {
			\mkdir($this->dir . '/releases/' . $release, 0o775, true);
			// as link-shared links it
			\symlink($this->dir . '/.ozone', $this->dir . '/releases/' . $release . '/.ozone');

			$this->writeList($this->dir . '/releases/' . $release, $release);
		}

		\symlink($this->dir . '/releases/r1', $this->dir . '/current');

		self::assertSame('r1', $this->preload());

		// r2 goes live, or current goes back to it: preloaded once PHP restarts
		\unlink($this->dir . '/current');
		\symlink($this->dir . '/releases/r2', $this->dir . '/current');

		self::assertSame('r2', $this->preload());
	}

	public function testAReleaseWithoutAListPreloadsNothing(): void
	{
		// the list of another release is never a fallback
		\mkdir($this->dir . '/releases/r1', 0o775, true);
		\symlink($this->dir . '/releases/r1', $this->dir . '/current');

		$this->writeList($this->dir, 'project');

		self::assertNull($this->preload());
	}

	public function testPruningRemovesTheBuildOutputOfReleasesThatAreGone(): void
	{
		// r1 is live, r2 is on disk, r3 was pruned by an earlier deploy.
		foreach (['r1', 'r2', 'r3'] as $release) {
			\mkdir($this->dir . '/releases/' . $release, 0o775, true);
			\symlink($this->dir . '/.ozone', $this->dir . '/releases/' . $release . '/.ozone');
			$this->writeList($this->dir . '/releases/' . $release, $release);
		}

		\symlink($this->dir . '/releases/r1', $this->dir . '/current');

		$keyed = fn (string $release): string => $this->dir . '/.ozone/build/'
			. InstanceLayout::releaseKey($this->dir . '/releases/' . $release);

		$gone = $keyed('r3');
		self::remove($this->dir . '/releases/r3');

		// What a deployment made before build output was keyed, and what a release still running it
		// reads: not a release directory, so not an orphan.
		\mkdir($this->dir . '/.ozone/build/plugins/OZONE/Core/Db', 0o775, true);
		\file_put_contents($this->dir . '/.ozone/build/plugins/OZONE/Core/Db/Old.php', "<?php\n");

		self::assertSame(1, ProjectBuilder::pruneOrphans($this->dir . '/releases/r2'));

		self::assertDirectoryDoesNotExist($gone);
		self::assertDirectoryExists($keyed('r1'), 'the live release keeps what it compiled');
		self::assertDirectoryExists($keyed('r2'), 'and so does the one being built');
		self::assertFileExists($this->dir . '/.ozone/build/plugins/OZONE/Core/Db/Old.php');

		// Idempotent: nothing is left to remove.
		self::assertSame(0, ProjectBuilder::pruneOrphans($this->dir . '/releases/r2'));
	}

	public function testAProjectOutsideADeployRootPrunesNothing(): void
	{
		$this->writeList($this->dir, 'project');

		self::assertSame(0, ProjectBuilder::pruneOrphans($this->dir));
		self::assertFileExists(ProjectBuilder::preloadListFor($this->dir));
	}

	/**
	 * A list recording that the preload script read it: it lists no file, so nothing is compiled.
	 */
	private function writeList(string $release, string $label): void
	{
		$file = ProjectBuilder::preloadListFor($release);
		$dir  = \dirname($file);

		// as ProjectBuilder::write() creates it
		if (!\is_dir($dir)) {
			\mkdir($dir, 0o775, true);
		}

		// the list of a release is in the shared .ozone/, under that release's own directory, where
		// the script looks for it
		self::assertStringStartsWith(
			$this->dir . '/.ozone/build/',
			(string) \realpath($dir) . '/'
		);
		self::assertSame(InstanceLayout::releaseKey($release), \basename($dir));

		\file_put_contents(
			$file,
			"<?php\n\n\$GLOBALS['oz_preload_probe'] = " . \var_export($label, true) . ";\n\nreturn [];\n"
		);
	}

	/**
	 * Runs the preload script as PHP does when it starts, and tells which list it read.
	 */
	private function preload(): ?string
	{
		unset($GLOBALS['oz_preload_probe']);

		(static function (string $file): void {
			require $file;
		})($this->dir . '/.ozone/preload.php');

		$label = $GLOBALS['oz_preload_probe'] ?? null;

		return \is_string($label) ? $label : null;
	}

	private static function remove(string $path): void
	{
		if (\is_link($path) || \is_file($path)) {
			\unlink($path);

			return;
		}

		if (\is_dir($path)) {
			foreach (\scandir($path) ?: [] as $entry) {
				if ('.' !== $entry && '..' !== $entry) {
					self::remove($path . \DIRECTORY_SEPARATOR . $entry);
				}
			}

			\rmdir($path);
		}
	}
}
