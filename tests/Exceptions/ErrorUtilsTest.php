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

namespace OZONE\Tests\Exceptions;

use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\Utils\ErrorUtils;
use OZONE\Core\OZone;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Class ErrorUtilsTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Exceptions\Utils\ErrorUtils
 */
final class ErrorUtilsTest extends TestCase
{
	protected function setUp(): void
	{
		Settings::set('oz.logs', 'OZ_REDACT_SENSITIVE_DATA', true);
	}

	protected function tearDown(): void
	{
		Settings::unset('oz.logs', 'OZ_REDACT_SENSITIVE_DATA');
	}

	public function testTheErrorHandlersAreInstalledWhenAProjectBoots(): void
	{
		// Registered by the autoloader, so the project's boot file, its app file and every class
		// loaded for them are covered: an error there reached only PHP's own error log before.
		[$code, $out] = self::runAutoloading(
			"echo null === \\set_error_handler(null) ? 'none' : 'installed';",
			true
		);

		self::assertSame(0, $code, $out);
		self::assertStringContainsString('installed', $out);
	}

	public function testAToolThatOnlyAutoloadsThePackageKeepsItsOwnErrorHandling(): void
	{
		// Static analysis, the code style fixer, a build script: none of them boots a project, and
		// taking over their error handling and their shutdown would be none of our business.
		[$code, $out] = self::runAutoloading(
			"echo null === \\set_error_handler(null) ? 'none' : 'installed';",
			false
		);

		self::assertSame(0, $code, $out);
		self::assertStringContainsString('none', $out);
	}

	public function testANonFatalLastErrorIsNotReportedAsAShutdownError(): void
	{
		// A diagnostic raised while another handler was in place: it never reached ours, so PHP keeps
		// it as the last error and the shutdown function is the first to see it -- at the end of a run
		// that was otherwise clean, and nowhere near where it happened.
		[$code, , $log] = self::runAutoloading(
			'\\set_error_handler(static fn (): bool => false);'
				. "@include __DIR__ . '/not-here.php';"
				. '\\restore_error_handler();',
			true
		);

		self::assertSame(0, $code);
		self::assertStringContainsString('no error handler of ours was installed', $log);
		self::assertStringNotContainsString('OZone shutdown error', $log);
		self::assertStringContainsString('not-here.php', $log);
	}

	public function testAFatalIsStillReportedAsAShutdownError(): void
	{
		// The case the shutdown function is for: no error handler is called for a fatal, and no
		// `catch` sees it either. A constant a child redeclares without its parent's type is one, and
		// it is the shape of fatal a release reading another release's generated classes used to hit.
		$body = "\\file_put_contents(__DIR__ . '/probe_parent.php',"
			. " '<?php class OzProbeParent { public const array C = []; }');"
			. "\\file_put_contents(__DIR__ . '/probe_child.php',"
			. " '<?php class OzProbeChild extends OzProbeParent { public const C = []; }');"
			. "require __DIR__ . '/probe_parent.php';"
			. "require __DIR__ . '/probe_child.php';";

		[$code, , $log] = self::runAutoloading($body, true);

		self::assertNotSame(0, $code, 'a fatal ends the process');
		self::assertStringContainsString('OZone shutdown error', $log);
		self::assertStringNotContainsString('no error handler of ours was installed', $log);
		self::assertStringContainsString('OzProbeChild::C must be compatible', $log);
	}

	public function testRedactionCanBeDisabled(): void
	{
		Settings::set('oz.logs', 'OZ_REDACT_SENSITIVE_DATA', false);

		self::assertFalse(ErrorUtils::isRedactionEnabled());
		self::assertSame(['api_key' => 'k'], ErrorUtils::redactSensitiveData(['api_key' => 'k']));
	}

	public function testRedactionDefaultsToProductionMode(): void
	{
		Settings::unset('oz.logs', 'OZ_REDACT_SENSITIVE_DATA');

		self::assertSame(OZone::inProductionMode(), ErrorUtils::isRedactionEnabled());
	}

	public function testRedactsSecretLookingKeysRecursively(): void
	{
		$data = [
			'_token'  => 'abc',
			'field'   => 'email',
			'api_key' => 'k',
			'nested'  => [
				'password' => 'p',
				'pass_new' => 'n',
				'ok'       => 1,
			],
			0         => 'kept',
		];

		self::assertSame([
			'_token'  => ErrorUtils::REDACTED,
			'field'   => 'email',
			'api_key' => ErrorUtils::REDACTED,
			'nested'  => [
				'password' => ErrorUtils::REDACTED,
				'pass_new' => ErrorUtils::REDACTED,
				'ok'       => 1,
			],
			0         => 'kept',
		], ErrorUtils::redactSensitiveData($data));
	}

	public function testLoggedExceptionTextHasNoSecret(): void
	{
		$e = new ForbiddenException(null, ['_reason' => 'r', '_api_key' => 'super-secret-key']);

		self::assertStringNotContainsString('super-secret-key', ForbiddenException::throwableToString($e));
	}

	/**
	 * Runs a snippet in its own process, with the package autoloaded the way a project boots it or the
	 * way a tool does.
	 *
	 * @return array{0: null|int, 1: string, 2: string} the exit code, the output, the project log
	 */
	private static function runAutoloading(string $body, bool $as_project): array
	{
		$dir = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'oz_err_' . \bin2hex(\random_bytes(6));

		\mkdir($dir, 0o775, true);

		$autoload = \dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR . 'vendor'
			. \DIRECTORY_SEPARATOR . 'autoload.php';

		$script = $dir . \DIRECTORY_SEPARATOR . 'run.php';

		\file_put_contents($script, "<?php\n\n"
			// Before the autoload require, as a project's boot file does it.
			. ($as_project ? '\define(\'OZ_PROJECT_DIR\', ' . \var_export($dir . \DIRECTORY_SEPARATOR, true) . ");\n" : '')
			. 'require ' . \var_export($autoload, true) . ";\n"
			. $body . "\n");

		// No app boots here, so LogWriter falls back to `sys_get_temp_dir()`: TMPDIR puts that inside
		// this run's own directory instead of a file every test would share.
		$process = new Process([\PHP_BINARY, $script], $dir, ['TMPDIR' => $dir], null, 60.0);
		$process->run();

		$log = $dir . \DIRECTORY_SEPARATOR . 'ozone.log';

		$contents = \is_file($log) ? (string) \file_get_contents($log) : '';

		self::rmdir($dir);

		return [$process->getExitCode(), $process->getOutput() . $process->getErrorOutput(), $contents];
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
