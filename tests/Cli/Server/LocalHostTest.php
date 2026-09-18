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

namespace OZONE\Tests\Cli\Server;

use OZONE\Core\Cli\Server\Enums\PackageManager;
use OZONE\Core\Cli\Server\LocalHost;
use OZONE\Core\Cli\Server\ProvisionManifest;
use OZONE\Core\Cli\Server\ProvisionStep;
use OZONE\Core\Testing\Sandbox;
use PHPUnit\Framework\TestCase;

/**
 * The machine running `oz`, as a host: what provisioning and deploy read and run on it. The SSH host
 * answers the same questions over a connection (tests/Integration/Cli/SshHostTest.php).
 *
 * @internal
 *
 * @covers \OZONE\Core\Cli\Server\LocalHost
 * @covers \OZONE\Core\Cli\Server\ProvisionManifest
 */
final class LocalHostTest extends TestCase
{
	private string $dir;

	protected function setUp(): void
	{
		parent::setUp();

		$this->dir = \sys_get_temp_dir() . '/oz_local_host_' . \bin2hex(\random_bytes(4));

		\mkdir($this->dir . '/releases/b', 0o775, true);
		\mkdir($this->dir . '/releases/a', 0o775, true);
		\file_put_contents($this->dir . '/releases/not-a-dir', 'x');
		\symlink($this->dir . '/releases/b', $this->dir . '/current');
	}

	protected function tearDown(): void
	{
		Sandbox::remove($this->dir);

		parent::tearDown();
	}

	public function testRunsCommandsThroughAShell(): void
	{
		$host   = new LocalHost();
		$output = '';

		self::assertSame(0, $host->run('echo one | tr o O', $output));
		self::assertSame("One\n", $output);
		self::assertSame(3, $host->run('exit 3'));
	}

	public function testReadsPathsLinksAndDirectories(): void
	{
		$host = new LocalHost();

		self::assertTrue($host->exists($this->dir . '/current'));
		self::assertTrue($host->isDir($this->dir . '/current'));
		self::assertTrue($host->isLink($this->dir . '/current'));
		self::assertFalse($host->isLink($this->dir . '/releases'));
		self::assertSame($this->dir . '/releases/b', $host->readLink($this->dir . '/current'));
		self::assertNull($host->readLink($this->dir . '/releases'));
		self::assertSame(['a', 'b'], $host->listDirs($this->dir . '/releases'));
		self::assertSame([], $host->listDirs($this->dir . '/nope'));
	}

	public function testWritesReadsAndUploadsFiles(): void
	{
		$host = new LocalHost();

		self::assertTrue($host->writeFile($this->dir . '/deep/new/file.txt', "content\n"));
		self::assertSame("content\n", $host->readFile($this->dir . '/deep/new/file.txt'));
		self::assertNull($host->readFile($this->dir . '/missing.txt'));

		self::assertTrue($host->upload($this->dir . '/deep/new/file.txt', $this->dir . '/uploads/copy.txt'));
		self::assertFileEquals($this->dir . '/deep/new/file.txt', $this->dir . '/uploads/copy.txt');
	}

	public function testFindsBinariesOnPath(): void
	{
		$host = new LocalHost();

		self::assertTrue($host->hasBinary('sh'));
		self::assertFalse($host->hasBinary('oz-no-such-binary'));
		self::assertSame(\function_exists('posix_geteuid') && 0 === \posix_geteuid(), $host->isRoot());
	}

	public function testDetectionAndChecksAskTheHost(): void
	{
		$host = new LocalHost();

		self::assertSame(PackageManager::detect($host), PackageManager::detect());

		$step = new ProvisionStep('probe', 'A probe.', ['true'], static fn (LocalHost $h): bool => $h->hasBinary('sh'));

		self::assertTrue($step->isSatisfied($host));
		self::assertFalse((new ProvisionStep('probe', 'A probe.', ['true']))->isSatisfied($host));
	}

	public function testTheManifestIsReadAndSavedThroughTheHost(): void
	{
		$host = new LocalHost();
		$path = $this->dir . '/etc/ozone/provision.json';

		$manifest = ProvisionManifest::load($path, $host);

		self::assertSame([], $manifest->steps());

		$manifest->record(new ProvisionStep('php', 'PHP.', ['true']), ['true']);

		self::assertTrue($manifest->save());
		self::assertSame(['php'], ProvisionManifest::load($path, $host)->steps());
	}
}
