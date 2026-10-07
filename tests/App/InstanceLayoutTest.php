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

namespace OZONE\Tests\App;

use Override;
use OZONE\Core\App\InstanceLayout;
use PHPUnit\Framework\TestCase;

/**
 * Class InstanceLayoutTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\App\InstanceLayout
 */
final class InstanceLayoutTest extends TestCase
{
	private string $root = '';

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->root = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'oz_instance_' . \bin2hex(\random_bytes(6));

		\mkdir($this->root, 0o775, true);
	}

	#[Override]
	protected function tearDown(): void
	{
		self::rmdir($this->root);

		parent::tearDown();
	}

	public function testWhatABuildOwnsAndWhatARequestFillsAreSiblings(): void
	{
		$ds   = \DIRECTORY_SEPARATOR;
		$dir  = $this->root . $ds . InstanceLayout::DIR;
		$root = $this->root;

		self::assertSame($dir, InstanceLayout::path($root));
		self::assertSame($dir . $ds . 'build', InstanceLayout::buildPath($root));
		self::assertSame($dir . $ds . 'cache', InstanceLayout::cachePath($root));
		self::assertSame($dir . $ds . 'preload.php', InstanceLayout::preloadScriptPath($root));

		// Neither is inside the other: dropping the build output of a release never touches what
		// requests filled on demand, and clearing the caches never costs a rebuild.
		self::assertStringStartsNotWith(
			InstanceLayout::cachePath($root) . $ds,
			InstanceLayout::buildPath($root)
		);
		self::assertStringStartsNotWith(
			InstanceLayout::buildPath($root) . $ds,
			InstanceLayout::cachePath($root)
		);
	}

	public function testATrailingSeparatorOnTheRootIsNotDoubled(): void
	{
		self::assertSame(
			InstanceLayout::path($this->root),
			InstanceLayout::path($this->root . \DIRECTORY_SEPARATOR)
		);
	}

	public function testEveryPartJoinsUnderItsDirectory(): void
	{
		$ds = \DIRECTORY_SEPARATOR;

		self::assertSame(
			InstanceLayout::buildPath($this->root) . $ds . 'routes' . $ds . 'api' . $ds . 'api.key.php',
			InstanceLayout::buildPath($this->root, InstanceLayout::BUILD_ROUTES, 'api', 'api.key.php')
		);
		self::assertSame(
			InstanceLayout::cachePath($this->root) . $ds . 'cron.minute',
			InstanceLayout::cachePath($this->root, InstanceLayout::CACHE_CRON_MARKER)
		);
	}

	public function testADirectoryIsCreatedWhenMissing(): void
	{
		$build = InstanceLayout::buildDir($this->root, InstanceLayout::BUILD_ENV);
		$logs  = InstanceLayout::logsDir($this->root);

		self::assertSame(
			self::real(InstanceLayout::buildPath($this->root, InstanceLayout::BUILD_ENV)),
			self::real($build->getRoot())
		);
		self::assertSame(
			self::real(InstanceLayout::path($this->root, InstanceLayout::LOGS)),
			self::real($logs->getRoot())
		);
	}

	public function testAScopeCacheIsNamedAfterItsStateSlug(): void
	{
		// The same slug that names the scope's state under `data/`, so the two roots read alike:
		// `data/plugins/acme/files` beside `.ozone/cache/plugins/acme`.
		$slug  = 'plugins' . \DIRECTORY_SEPARATOR . 'acme';
		$cache = InstanceLayout::scopeCacheDir($this->root, $slug);

		self::assertSame(
			self::real(InstanceLayout::cachePath($this->root, $slug)),
			self::real($cache->getRoot())
		);
		self::assertDirectoryExists($cache->getRoot());
	}

	/**
	 * A path with no trailing separator and every link resolved, so two spellings of one directory
	 * compare equal.
	 */
	private static function real(string $path): string
	{
		$real = \realpath($path);

		return \rtrim(false === $real ? $path : $real, '/\\');
	}

	private static function rmdir(string $dir): void
	{
		if (!\is_dir($dir)) {
			return;
		}

		foreach (\array_diff(\scandir($dir) ?: [], ['.', '..']) as $entry) {
			$path = $dir . \DIRECTORY_SEPARATOR . $entry;

			\is_dir($path) ? self::rmdir($path) : \unlink($path);
		}

		\rmdir($dir);
	}
}
