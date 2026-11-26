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
use OZONE\Core\Cli\Server\LocalHost;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Runtime\CgiRuntime;
use OZONE\Core\Runtime\Runtime;
use OZONE\Tests\Support\ExecutableTree;
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
#[CoversClass(LocalHost::class)]
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
		$this->root = ExecutableTree::create();
	}

	#[Override]
	protected function tearDown(): void
	{
		\chdir($this->cwd);
		ExecutableTree::remove($this->root);

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
		$this->expectExceptionMessageIsOrContains('any directory: there is no PHP_BINARY, PHP_BINDIR or PATH');

		Process::findPhpBinary(null, '', 'frankenphp', '', false, self::VERSION);
	}

	/**
	 * Served under CGI, the request is environment. What a PHP process started from there is given
	 * is what {@see Process::CHILD_ENV} keeps, whatever else the server put there; a command line
	 * passes its environment on.
	 */
	public function testAProcessServingRequestsPassesOnlyWhatItKeeps(): void
	{
		$inherited = [
			'PATH'                  => '/usr/local/bin:/usr/bin',
			'HOME'                  => '/var/www',
			'TMPDIR'                => '/srv/tmp',
			'LANG'                  => 'C.UTF-8',
			'LC_ALL'                => 'C.UTF-8',
			'TZ'                    => 'UTC',
			'HTTP_COOKIE'           => 'OZSID=secret',
			'HTTP_AUTHORIZATION'    => 'Bearer secret',
			'REQUEST_METHOD'        => 'GET',
			'SSL_CLIENT_S_DN'       => 'CN=someone',
			'UNIQUE_ID'             => 'aBc',
			'CONTEXT_DOCUMENT_ROOT' => '/srv/www',
			'RR_MODE'               => 'http',
			'FRANKENPHP_WORKER'     => '1',
			'OZ_RUNTIME'            => 'worker',
			'APP_SECRET'            => 'a site\'s own FastCGI parameter',
		];

		$env = Process::childEnv(false, $inherited, ['OZ_JOB' => 'given', 'HOME' => '/home/job']);

		self::assertSame([
			'PATH'                  => '/usr/local/bin:/usr/bin',
			'HOME'                  => '/home/job',
			'TMPDIR'                => '/srv/tmp',
			'LANG'                  => 'C.UTF-8',
			'LC_ALL'                => 'C.UTF-8',
			'TZ'                    => 'UTC',
			'HTTP_COOKIE'           => false,
			'HTTP_AUTHORIZATION'    => false,
			'REQUEST_METHOD'        => false,
			'SSL_CLIENT_S_DN'       => false,
			'UNIQUE_ID'             => false,
			'CONTEXT_DOCUMENT_ROOT' => false,
			'RR_MODE'               => false,
			'FRANKENPHP_WORKER'     => false,
			'OZ_RUNTIME'            => false,
			'APP_SECRET'            => false,
			'OZ_JOB'                => 'given',
		], $env);

		self::assertSame([], Process::childEnv(false, [], null));

		// The command line: what it was given, and the rest inherited.
		self::assertNull(Process::childEnv(true, $inherited, null));
		self::assertSame(['OZ_JOB' => 'given'], Process::childEnv(true, $inherited, ['OZ_JOB' => 'given']));
	}

	/**
	 * Whoever built the process, and however it is started: while this process serves requests,
	 * it is given the kept environment and what its caller passed, and nothing else.
	 */
	public function testEveryProcessStartedWhileServingRequestsGetsTheKeptEnvironment(): void
	{
		// In the environment as a server's variable is: Symfony passes on what `getenv()` and
		// `$_SERVER` both hold.
		\putenv('OZ_PROCESS_LEAK_PROBE=leaked');
		$_SERVER['OZ_PROCESS_LEAK_PROBE'] = 'leaked';
		Runtime::set(new CgiRuntime());

		try {
			$built = new Process(['/usr/bin/env'], null, ['OZ_GIVEN_PROBE' => 'given']);

			$shell = Process::fromShellCommandline('/usr/bin/env');

			$shell->setEnv(['OZ_SET_PROBE' => 'set']);

			$started = new Process(['/usr/bin/env']);

			$started->start(null, ['OZ_STARTED_PROBE' => 'started']);
			$started->wait();

			$outputs = [
				'built'   => [$built->mustRun()->getOutput(), 'OZ_GIVEN_PROBE=given'],
				'shell'   => [$shell->mustRun()->getOutput(), 'OZ_SET_PROBE=set'],
				'started' => [$started->getOutput(), 'OZ_STARTED_PROBE=started'],
			];

			foreach ($outputs as $how => [$output, $given]) {
				self::assertStringNotContainsString('OZ_PROCESS_LEAK_PROBE', $output, $how);
				self::assertStringContainsString($given, $output, $how);
				self::assertStringContainsString('PATH=' . \getenv('PATH') . "\n", $output, $how);
			}
		} finally {
			Runtime::reset();
		}

		// The command line passes its environment on.
		try {
			self::assertStringContainsString(
				'OZ_PROCESS_LEAK_PROBE=leaked',
				new Process(['/usr/bin/env'])->mustRun()->getOutput()
			);
		} finally {
			\putenv('OZ_PROCESS_LEAK_PROBE');
			unset($_SERVER['OZ_PROCESS_LEAK_PROBE']);
		}
	}

	/**
	 * Every PHP process is built the one way: this PHP, and, on this command line, its environment.
	 */
	public function testAPhpProcessIsStartedWithThePhpBinary(): void
	{
		$process = Process::php(['-r', 'echo getenv("OZ_PHP_PROCESS_PROBE");'], null, ['OZ_PHP_PROCESS_PROBE' => 'yes']);

		self::assertSame(\escapeshellarg(\PHP_BINARY) . " '-r' 'echo getenv(\"OZ_PHP_PROCESS_PROBE\");'", $process->getCommandLine());
		self::assertSame('yes', $process->mustRun()->getOutput());
	}

	public function testThePhpNamesAreThisVersionsFirst(): void
	{
		self::assertSame(['php8.5', 'php85', 'php'], Process::phpNames('8.5'));
		self::assertSame(['php10.12', 'php1012', 'php'], Process::phpNames('10.12'));
	}

	/**
	 * This machine's PATH, as {@see LocalHost} reads it: the directories in order, and in each the
	 * names in order; a directory or a file that cannot be run is passed over.
	 */
	public function testThisMachineIsAskedForAnExecutableOnItsPath(): void
	{
		\mkdir($this->root . '/a/php', 0o775, true);
		$this->file('a/php85', 0o644);

		self::assertTrue(\is_dir($this->root . '/a/php'));

		$b_php   = $this->exe('b/php');
		$c_php85 = $this->exe('c/php85');
		$path    = \getenv('PATH');
		$host    = new LocalHost();

		\putenv('PATH=' . \implode(\PATH_SEPARATOR, ['', $this->root . '/a', $this->root . '/b', $this->root . '/c']));

		try {
			// A name is preferred over another within a directory only.
			self::assertSame($b_php, $host->findExecutable(['php85', 'php']));
			self::assertSame($c_php85, $host->findExecutable(['php85']));
			self::assertNull($host->findExecutable(['php8.5']));
			self::assertNull($host->findExecutable([]));

			self::assertTrue($host->hasBinary('php'));
			self::assertFalse($host->hasBinary('php8.5'));

			// `a/php` is a directory, found on PATH before `b/php`: never a binary.
			\putenv('PATH=' . $this->root . '/a');

			self::assertFalse($host->hasBinary('php'));
			self::assertFalse($host->hasBinary('php85'));
		} finally {
			\putenv(false === $path ? 'PATH' : 'PATH=' . $path);
		}

		self::assertTrue($host->isExecutableFile($b_php));
		self::assertFalse($host->isExecutableFile($this->root . '/a/php'));
		self::assertFalse($host->isExecutableFile($this->root . '/a/php85'));
	}

	/**
	 * The PHP of a host deployed to: the one given, which must be an executable file there; else,
	 * on this machine, this machine's.
	 */
	public function testTheHostsPhpIsTheOneGivenOrThisMachines(): void
	{
		$host  = new LocalHost();
		$given = $this->exe('opt/php/bin/php');

		self::assertSame($given, Process::hostPhpBinary($host, $given));
		self::assertSame(\PHP_BINARY, Process::hostPhpBinary($host));
		self::assertSame(\PHP_BINARY, Process::hostPhpBinary($host, ''));

		foreach (['opt/php/bin/php', $this->root . '/nowhere/php', $this->file('opt/php/bin/php-ro', 0o644)] as $bad) {
			try {
				Process::hostPhpBinary($host, $bad);
				self::fail('An unusable --php was accepted: ' . $bad);
			} catch (RuntimeException $e) {
				self::assertStringContainsString(
					\sprintf('--php is "%s", which is not the absolute path of an executable file on this machine', $bad),
					$e->getMessage()
				);
			}
		}
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
		return ExecutableTree::file($this->root, $relative);
	}

	private function file(string $relative, int $mode): string
	{
		return ExecutableTree::file($this->root, $relative, $mode);
	}
}
