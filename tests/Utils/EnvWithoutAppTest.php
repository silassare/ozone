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

namespace OZONE\Tests\Utils;

use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\OZone;
use OZONE\Core\Utils\Env;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * `env()` answers in a process with no app running.
 *
 * A project's settings file is PHP and reads `env()`, and reading one is not always something an app
 * does: a tool, a deploy step and the test kit each read one before there is an app to boot.
 *
 * @internal
 */
#[CoversClass(Env::class)]
#[CoversClass(OZone::class)]
final class EnvWithoutAppTest extends TestCase
{
	/** The variables this test puts in its own process, removed again whatever it asserted. */
	private array $exported = [];

	protected function tearDown(): void
	{
		foreach ($this->exported as $key) {
			\putenv($key);
		}

		$this->exported = [];

		parent::tearDown();
	}

	public function testAValueIsReadWithNoAppRunning(): void
	{
		$answer = self::inAProcessWithNoApp(
			'echo \var_export(env("OZ_PROBE_VALUE", "fallback"), true);',
			['OZ_PROBE_VALUE' => 'from-the-process']
		);

		self::assertSame("'from-the-process'", $answer);
	}

	public function testTheDefaultIsTheAnswerWhenNothingHasTheKey(): void
	{
		$answer = self::inAProcessWithNoApp('echo \var_export(env("OZ_PROBE_ABSENT", "fallback"), true);');

		self::assertSame("'fallback'", $answer);
	}

	/**
	 * A project's own `.env` is where a value should come from, and a process that belongs to a
	 * project knows which one it is before an app is built.
	 */
	public function testTheProjectFileIsPreferredToTheProcess(): void
	{
		$dir = self::aDirHoldingAnEnvFile();

		try {
			$answer = self::inAProcessWithNoApp(
				'echo \var_export(env("OZ_PROBE_VALUE", "fallback"), true);',
				['OZ_PROBE_VALUE' => 'from-the-process'],
				$dir
			);

			self::assertSame("'from-the-file'", $answer);
		} finally {
			self::removeDir($dir);
		}
	}

	/**
	 * A file beside the working directory is not a project's.
	 *
	 * `OZ_PROJECT_DIR` falls back to the working directory, so every process has one: reading a
	 * `.env` next to it would answer out of whatever directory a tool was started in, which is how
	 * this read OZone's own `.env` while it was being written.
	 */
	public function testAFileBesideTheWorkingDirectoryIsNotReadAsAProjects(): void
	{
		$dir = self::aDirHoldingAnEnvFile();

		try {
			$answer = self::inAProcessWithNoApp(
				'echo \var_export(env("OZ_PROBE_VALUE", "fallback"), true);',
				['OZ_PROBE_VALUE' => 'from-the-process'],
				null,
				$dir
			);

			self::assertSame("'from-the-process'", $answer);
		} finally {
			self::removeDir($dir);
		}
	}

	/**
	 * The parser reads the process environment, so a value is read here as it is in a file instead of
	 * by a second set of rules.
	 */
	public function testAProcessValueIsReadAsAFileValueIs(): void
	{
		$this->export([
			'OZ_PROBE_TRUE'   => 'true',
			'OZ_PROBE_FALSE'  => 'false',
			'OZ_PROBE_INT'    => '3306',
			'OZ_PROBE_FLOAT'  => '1.5',
			'OZ_PROBE_STRING' => 'a word',
		]);

		$env = Env::ofProcess();

		self::assertTrue($env->get('OZ_PROBE_TRUE'));
		self::assertFalse($env->get('OZ_PROBE_FALSE'));
		self::assertSame(3306, $env->get('OZ_PROBE_INT'));
		self::assertSame(1.5, $env->get('OZ_PROBE_FLOAT'));
		self::assertSame('a word', $env->get('OZ_PROBE_STRING'));
	}

	/**
	 * A value no env file could carry on one line is still the value the process gave.
	 */
	public function testAValueHoldingALineBreakIsKeptAsItIs(): void
	{
		$this->export(['OZ_PROBE_LINES' => "first\nsecond"]);

		self::assertSame("first\nsecond", Env::ofProcess()->get('OZ_PROBE_LINES'));
	}

	public function testItHasNoSignatureOfAFileAndNoFileToPatch(): void
	{
		$env = Env::ofProcess();

		self::assertSame('', $env->getPath());
		self::assertNotSame('', $env->getSignature());

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('The process environment has no file to patch.');

		$env->patch(['OZ_PROBE_VALUE' => 'written']);
	}

	private function export(array $values): void
	{
		foreach ($values as $key => $value) {
			\putenv($key . '=' . $value);
			$this->exported[] = $key;
		}
	}

	/**
	 * Runs a snippet in a process holding OZone's autoloader and no app, as a standalone tool has it.
	 *
	 * @param array<string, string> $env
	 */
	private static function inAProcessWithNoApp(
		string $snippet,
		array $env = [],
		?string $project = null,
		?string $cwd = null
	): string {
		$autoload = \dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR . 'vendor' . \DIRECTORY_SEPARATOR . 'autoload.php';
		$boot     = null === $project ? '' : 'define("OZ_PROJECT_DIR", $argv[2]);';
		$process  = new Process(
			[\PHP_BINARY, '-r', $boot . 'require $argv[1];' . $snippet, $autoload, $project ?? ''],
			$cwd,
			$env
		);

		$process->run();

		if (0 !== $process->getExitCode()) {
			self::fail($process->getErrorOutput() . $process->getOutput());
		}

		return $process->getOutput();
	}

	/** A directory of this run's own, holding a `.env` that answers `OZ_PROBE_VALUE`. */
	private static function aDirHoldingAnEnvFile(): string
	{
		$dir = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'oz_env_project_' . \bin2hex(\random_bytes(6));

		\mkdir($dir);
		\file_put_contents($dir . \DIRECTORY_SEPARATOR . '.env', "OZ_PROBE_VALUE=\"from-the-file\"\n");

		return $dir;
	}

	/** Dot files included, since a `.env` and the compiled copy beside it are both of them. */
	private static function removeDir(string $dir): void
	{
		foreach (\scandir($dir) ?: [] as $name) {
			if ('.' === $name || '..' === $name) {
				continue;
			}

			$file = $dir . \DIRECTORY_SEPARATOR . $name;

			\is_dir($file) ? self::removeDir($file) : \unlink($file);
		}

		\rmdir($dir);
	}
}
