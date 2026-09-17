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

use OZONE\Core\Utils\Env;
use PHPUnit\Framework\TestCase;
use PHPUtils\Env\EnvParser;
use Symfony\Component\Process\Process;

/**
 * Env reads through a compiled copy of the file, named after its path and content.
 *
 * @covers \OZONE\Core\Utils\Env
 *
 * @internal
 */
final class EnvCompiledTest extends TestCase
{
	private string $file;

	protected function setUp(): void
	{
		parent::setUp();

		$this->file = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'oz_env_' . \bin2hex(\random_bytes(6)) . '.env';

		\file_put_contents($this->file, "A=1\nB=\"two\"\nC=true\nD=1.5\n");
	}

	protected function tearDown(): void
	{
		foreach ([$this->file, ...self::compiledCopies($this->file)] as $file) {
			if (\is_file($file)) {
				\unlink($file);
			}
		}

		parent::tearDown();
	}

	public function testReadsWhatTheParserReads(): void
	{
		$env    = new Env($this->file);
		$parser = EnvParser::fromFile($this->file);

		foreach (['A', 'B', 'C', 'D'] as $key) {
			self::assertSame($parser->getEnv($key), $env->get($key), $key);
		}

		self::assertSame('fallback', $env->get('MISSING', 'fallback'));
		self::assertCount(1, self::compiledCopies($this->file));

		// And the same from the compiled copy.
		self::assertSame($parser->getEnv('B'), (new Env($this->file))->get('B'));
	}

	public function testPatchNeedsNoRunningApp(): void
	{
		// A process with OZone's autoloader and no app booted, as a standalone tool has it.
		$autoload = \dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR . 'vendor' . \DIRECTORY_SEPARATOR . 'autoload.php';
		$script   = 'require $argv[1]; (new OZONE\Core\Utils\Env($argv[2]))->patch(["B" => "patched", "E" => 5]);';
		$process  = new Process([\PHP_BINARY, '-r', $script, $autoload, $this->file]);

		$process->run();

		self::assertSame(0, $process->getExitCode(), $process->getErrorOutput() . $process->getOutput());

		$parser = EnvParser::fromFile($this->file);

		self::assertSame('patched', $parser->getEnv('B'));
		self::assertSame(5, $parser->getEnv('E'));
		self::assertSame($parser->getEnv('A'), (new Env($this->file))->get('A'));
	}

	public function testAnEditMakesANewCopy(): void
	{
		new Env($this->file);

		$before = self::compiledCopies($this->file);

		\file_put_contents($this->file, "A=2\n");

		$env = new Env($this->file);

		self::assertSame(EnvParser::fromFile($this->file)->getEnv('A'), $env->get('A'));
		self::assertNull($env->get('B'));

		$after = self::compiledCopies($this->file);

		self::assertCount(1, $after);
		self::assertNotSame($before, $after);
	}

	public function testAPatchIsReadAtOnce(): void
	{
		$env = new Env($this->file);

		$env->upset('E', 'five');

		self::assertSame('five', $env->get('E'));
		self::assertSame(EnvParser::fromFile($this->file)->getEnv('A'), $env->get('A'));
	}

	/**
	 * @return string[]
	 */
	private static function compiledCopies(string $file): array
	{
		$ds  = \DIRECTORY_SEPARATOR;
		$dir = \dirname($file) . $ds . '.ozone' . $ds . 'cache' . $ds . 'env';

		return \glob($dir . $ds . \hash('xxh128', $file) . '.*.php') ?: [];
	}
}
