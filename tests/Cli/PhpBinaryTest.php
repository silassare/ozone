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

namespace OZONE\Tests\Cli;

use Override;
use OZONE\Core\Cli\Process;
use OZONE\Core\Exceptions\RuntimeException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The PHP binary a PHP process is started with ({@see Process::phpBinary()}), each rule asked with
 * the facts of a process given: a directory tree of executable files stands for a machine, and
 * nothing in it is ever run.
 *
 * @internal
 */
#[CoversClass(Process::class)]
final class PhpBinaryTest extends TestCase
{
	private const string VERSION = '8.5';

	private const array SUSPECT = ['type' => 'config', 'group' => 'oz.config', 'key' => 'OZ_PHP_CLI_BINARY'];

	private string $root;

	private string $cwd;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->cwd  = (string) \getcwd();
		$this->root = self::fixtureRoot() . \DIRECTORY_SEPARATOR . 'oz_php_binary_' . \bin2hex(\random_bytes(4));

		\mkdir($this->root, 0o775, true);
	}

	#[Override]
	protected function tearDown(): void
	{
		\chdir($this->cwd);
		self::remove($this->root);

		parent::tearDown();
	}

	public function testTheSettingWinsWhateverThisProcessIs(): void
	{
		$cli  = $this->exe('usr/bin/php');
		$set  = $this->exe('opt/php/bin/php');
		$path = $this->root . '/usr/bin';

		self::assertSame($set, Process::findPhpBinary($set, $cli, 'cli', $path, $path, self::VERSION));
		self::assertSame($set, Process::findPhpBinary($set, '', 'fpm-fcgi', $path, $path, self::VERSION));
	}

	/**
	 * A binary the project named is never swapped for a guess, even with one at hand.
	 */
	#[DataProvider('provideASettingThatIsNotAnExecutableFileIsRefusedNamingItCases')]
	public function testASettingThatIsNotAnExecutableFileIsRefusedNamingIt(string $case): void
	{
		$cli = $this->exe('usr/bin/php');

		// A relative path that names an executable file from here: what it names depends on the
		// directory a process happens to run in.
		\chdir($this->root);

		$configured = match ($case) {
			'relative'       => 'usr/bin/php',
			'missing'        => $this->root . '/nowhere/php',
			'a directory'    => $this->root . '/usr/bin',
			'not executable' => $this->file('usr/bin/php-not-exe', 0o644),
			default          => 85,
		};

		try {
			Process::findPhpBinary($configured, $cli, 'cli', $this->root . '/usr/bin', false, self::VERSION);
			self::fail('An unusable OZ_PHP_CLI_BINARY was accepted: ' . $case);
		} catch (RuntimeException $e) {
			self::assertStringContainsString('OZ_PHP_CLI_BINARY is ', $e->getMessage());
			self::assertStringContainsString('not the absolute path of an executable file', $e->getMessage());
			self::assertSame(self::SUSPECT, $e->getData(true)['_suspect'] ?? null);
		}
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function provideASettingThatIsNotAnExecutableFileIsRefusedNamingItCases(): iterable
	{
		foreach (['relative', 'missing', 'a directory', 'not executable', 'not a string'] as $case) {
			yield $case => [$case];
		}
	}

	/**
	 * The command line's own binary, and the built-in server's, which is the same executable.
	 */
	#[DataProvider('provideACommandLineProcessStartsWithItsOwnBinaryCases')]
	public function testACommandLineProcessStartsWithItsOwnBinary(string $sapi): void
	{
		$own = $this->exe('opt/php85/bin/php-custom');

		// A php elsewhere is not wanted: this process is one.
		$this->exe('usr/bin/php');

		self::assertSame(
			$own,
			Process::findPhpBinary(null, $own, $sapi, $this->root . '/usr/bin', $this->root . '/usr/bin', self::VERSION)
		);
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function provideACommandLineProcessStartsWithItsOwnBinaryCases(): iterable
	{
		yield 'cli' => ['cli'];

		yield 'cli-server' => ['cli-server'];
	}

	/**
	 * php-cgi, or phpdbg, which does not run a script given as an argument either: the CLI beside it.
	 */
	public function testAnotherSapiStartsWithThePhpBesideItsBinary(): void
	{
		$cgi  = $this->exe('usr/local/bin/php-cgi');
		$php  = $this->exe('usr/local/bin/php');
		$path = $this->exe('usr/bin/php');

		self::assertSame($php, Process::findPhpBinary(null, $cgi, 'cgi-fcgi', '', \dirname($path), self::VERSION));
		self::assertSame($php, Process::findPhpBinary(null, $cgi, 'phpdbg', '', \dirname($path), self::VERSION));
	}

	public function testThisVersionsOwnNameComesFirst(): void
	{
		$fpm = $this->exe('usr/sbin/php-fpm8.5');

		$this->exe('usr/sbin/php');
		$this->exe('usr/sbin/php85');

		$versioned = $this->exe('usr/sbin/php8.5');

		self::assertSame($versioned, Process::findPhpBinary(null, $fpm, 'fpm-fcgi', '', false, self::VERSION));

		\unlink($versioned);

		// Alpine's spelling.
		self::assertSame(
			$this->root . '/usr/sbin/php85',
			Process::findPhpBinary(null, $fpm, 'fpm-fcgi', '', false, self::VERSION)
		);
	}

	/**
	 * Debian: PHP-FPM in sbin/, its CLI in bin/, and a pool whose environment is cleared, PATH
	 * with it.
	 */
	public function testThenTheDirectoryThisBuildInstalledItsBinariesIn(): void
	{
		$fpm = $this->exe('usr/sbin/php-fpm8.5');
		$cli = $this->exe('usr/bin/php8.5');

		self::assertSame($cli, Process::findPhpBinary(null, $fpm, 'fpm-fcgi', \dirname($cli), false, self::VERSION));
	}

	/**
	 * FrankenPHP leaves PHP_BINARY empty.
	 */
	public function testThenPath(): void
	{
		\mkdir($this->root . '/empty-bindir');

		$this->exe('first/not-php');

		$php  = $this->exe('second/php');
		$path = \implode(\PATH_SEPARATOR, ['', $this->root . '/first', $this->root . '/second']);

		self::assertSame(
			$php,
			Process::findPhpBinary(null, '', 'frankenphp', $this->root . '/empty-bindir', $path, self::VERSION)
		);
	}

	/**
	 * A SAPI other than the command line's has just shown its binary not to be one, whatever its name.
	 */
	public function testThisProcesssOwnBinaryIsNeverACandidate(): void
	{
		$own = $this->exe('srv/bin/php');
		$cli = $this->exe('usr/bin/php');

		\symlink($own, $this->root . '/srv/bin/php8.5');

		self::assertSame(
			$cli,
			Process::findPhpBinary(null, $own, 'fpm-fcgi', $this->root . '/srv/bin', \dirname($cli), self::VERSION)
		);
	}

	/**
	 * A file that cannot be run, and a directory, are passed over.
	 */
	public function testOnlyAnExecutableFileIsACandidate(): void
	{
		$fpm = $this->exe('usr/sbin/php-fpm');

		$this->file('usr/sbin/php', 0o644);
		\mkdir($this->root . '/usr/sbin/php8.5');

		$cli = $this->exe('usr/bin/php');

		self::assertSame($cli, Process::findPhpBinary(null, $fpm, 'fpm-fcgi', '', \dirname($cli), self::VERSION));
		self::assertNull(Process::which('php', [$this->root . '/usr/sbin']));
		self::assertSame($cli, Process::which('php', [$this->root . '/usr/sbin', \dirname($cli)]));
	}

	public function testNothingFoundIsAnErrorNamingTheSetting(): void
	{
		$fpm = $this->exe('usr/sbin/php-fpm');

		\mkdir($this->root . '/usr/bin');

		try {
			Process::findPhpBinary(null, $fpm, 'fpm-fcgi', $this->root . '/usr/bin', false, self::VERSION);
			self::fail('A PHP binary was answered where there is none.');
		} catch (RuntimeException $e) {
			self::assertStringContainsString('"fpm-fcgi" SAPI', $e->getMessage());
			self::assertStringContainsString('none of php8.5, php85, php is in ', $e->getMessage());
			self::assertStringContainsString($this->root . '/usr/sbin', $e->getMessage());
			self::assertStringContainsString('Set OZ_PHP_CLI_BINARY (oz.config, or the .env)', $e->getMessage());
			self::assertSame(self::SUSPECT, $e->getData(true)['_suspect'] ?? null);
		}

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('any directory: there is no PHP_BINARY, PHP_BINDIR or PATH');

		Process::findPhpBinary(null, '', 'frankenphp', '', false, self::VERSION);
	}

	/**
	 * This suite is the command line, and its sandbox sets nothing.
	 */
	public function testThisProcessStartsWithItsOwnBinary(): void
	{
		self::assertNull(Process::configuredPhpBinary());
		self::assertSame(\PHP_BINARY, Process::phpBinary());
	}

	/**
	 * A file of the fixture machine, made executable.
	 */
	private function exe(string $relative): string
	{
		return $this->file($relative, 0o755);
	}

	private function file(string $relative, int $mode): string
	{
		$path = $this->root . \DIRECTORY_SEPARATOR . $relative;

		if (!\is_dir(\dirname($path))) {
			\mkdir(\dirname($path), 0o775, true);
		}

		// Never run: the resolver only looks.
		\file_put_contents($path, "#!/bin/sh\nexit 99\n");
		\chmod($path, $mode);

		return $path;
	}

	/**
	 * A directory whose files can be executable. The image's TMPDIR is a tmpfs mounted `noexec`,
	 * where `is_executable()` is false of every file, which would make each case here pass or fail
	 * for that reason alone; so the first temporary directory that can is taken, and one that cannot
	 * fails the test rather than passing it.
	 */
	private static function fixtureRoot(): string
	{
		foreach ([\sys_get_temp_dir(), '/tmp', '/var/tmp'] as $dir) {
			$probe = $dir . \DIRECTORY_SEPARATOR . 'oz_php_binary_probe_' . \getmypid();

			if (!\is_dir($dir) || !\is_writable($dir) || false === @\file_put_contents($probe, '')) {
				continue;
			}

			\chmod($probe, 0o755);
			$executable = \is_executable($probe);
			\unlink($probe);

			if ($executable) {
				return $dir;
			}
		}

		self::fail('No temporary directory here can hold an executable file.');
	}

	private static function remove(string $path): void
	{
		if (\is_link($path) || \is_file($path)) {
			\unlink($path);

			return;
		}

		if (!\is_dir($path)) {
			return;
		}

		foreach (\scandir($path) ?: [] as $entry) {
			if ('.' !== $entry && '..' !== $entry) {
				self::remove($path . \DIRECTORY_SEPARATOR . $entry);
			}
		}

		\rmdir($path);
	}
}
