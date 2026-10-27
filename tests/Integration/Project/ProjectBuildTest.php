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

use OZONE\Core\App\InstanceLayout;
use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * `oz project build` in a production project: what requests would compile at first use is there
 * before the first one.
 *
 * @internal
 */
#[CoversNothing]
final class ProjectBuildTest extends TestCase
{
	private static OZTestProject $proj;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$proj = OZTestProject::create('project-build', fresh: true);
		$ns   = $proj->getNamespace();

		$proj->writeEnv([
			'ENV_MODE'    => 'production',
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $proj->getPath() . '/project_build_test.sqlite',
		]);

		$proj->writeFileFromStub('TestRoutesProvider', 'app/TestRoutesProvider.php', ['namespace' => $ns]);
		$proj->setSetting('oz.routes.api', "{$ns}\\TestRoutesProvider", true);

		self::$proj = $proj;
	}

	public static function tearDownAfterClass(): void
	{
		if (isset(self::$proj)) {
			self::$proj->destroy();
		}

		parent::tearDownAfterClass();
	}

	public function testBuildsWhatRequestsWouldCompile(): void
	{
		$proc = self::$proj->oz('project', 'build', '--clear', '--no-preload');
		$proc->mustRun();

		$out = $proc->getOutput();

		self::assertStringContainsString('scope "api"', $out, $out);
		self::assertNotEmpty(self::buildFiles('env/*.php'));
		self::assertNotEmpty(self::buildFiles('settings/*.php'));
		self::assertCount(1, self::buildFiles('classmap.*.php'));
		self::assertCount(1, self::buildFiles('routes/api/api.*.php'));
		self::assertFileDoesNotExist(self::$proj->getPath() . '/.ozone/preload.php');
	}

	public function testClassicModeWritesThePreloadScript(): void
	{
		self::$proj->oz('project', 'build')->mustRun();

		// what opcache.preload points to, which preloads the list of the live release
		self::assertFileExists(self::$proj->getPath() . '/.ozone/preload.php');

		$lists = self::buildFiles('preload.php');

		self::assertCount(1, $lists);

		$files = require $lists[0];

		self::assertIsArray($files);
		self::assertNotEmpty($files);

		foreach ($files as $file) {
			self::assertFileExists($file);
			self::assertStringNotContainsString('/.ozone/', $file);
			// the test kit is never loaded by a request
			self::assertStringNotContainsString('/oz/Testing/', $file);
		}
	}

	public function testARequestUsesTheBuiltRouteTable(): void
	{
		self::$proj->oz('project', 'build', '--skip-orm', '--no-preload')->mustRun();

		$tables = self::buildFiles('routes/api/api.*.php');

		[$server, $host, $port] = self::$proj->startServer('api');

		try {
			$body = \file_get_contents("http://{$host}:{$port}/test-ping", false, \stream_context_create(['http' => [
				'ignore_errors' => true,
				'header'        => "Accept: application/json\r\n",
			]]));

			self::assertIsString($body);
			self::assertTrue(\json_decode($body, true)['data']['pong'] ?? false, $body);

			// Routed through the table the build compiled: none compiled since.
			self::assertSame($tables, self::buildFiles('routes/api/api.*.php'));
		} finally {
			$server->stop(3);
		}
	}

	public function testDoctorFindsTheBuildUpToDate(): void
	{
		self::$proj->oz('project', 'build', '--skip-orm')->mustRun();

		self::assertSame(['Production build' => 'ok', 'Preload' => 'ok'], self::buildChecks());
	}

	public function testDoctorWarnsOfAStaleBuild(): void
	{
		$ns    = self::$proj->getNamespace();
		$probe = self::$proj->getPath() . '/app/StaleBuildProbe.php';

		// A class the class map does not have, and a preloaded file changed since the build.
		self::$proj->writeFile(
			'app/StaleBuildProbe.php',
			"<?php\n\ndeclare(strict_types=1);\n\nnamespace {$ns};\n\nfinal class StaleBuildProbe {}\n"
		);
		\touch(self::$proj->getPath() . '/app/TestRoutesProvider.php', \time() + 10);

		try {
			self::assertSame(['Production build' => 'warn', 'Preload' => 'warn'], self::buildChecks());
		} finally {
			\unlink($probe);
		}
	}

	public function testWhatAScopeBuildPrintsBesidesItsReportIsReported(): void
	{
		$boot     = self::$proj->getPath() . '/app/boot.php';
		$original = (string) \file_get_contents($boot);

		// Each scope is built in a process of its own and its output used to be read for the report
		// and dropped. PHP puts a warning it raises while no handler is installed on one of these two.
		self::$proj->writeFile(
			'app/boot.php',
			$original . \PHP_EOL
				. "\\fwrite(\\STDOUT, 'OZ-NOISE-OUT' . \\PHP_EOL);" . \PHP_EOL
				. "\\fwrite(\\STDERR, 'OZ-NOISE-ERR' . \\PHP_EOL);" . \PHP_EOL
		);

		try {
			$proc = self::$proj->oz('project', 'build', '--skip-orm', '--no-preload');
			$proc->mustRun();

			$out = $proc->getOutput() . $proc->getErrorOutput();

			self::assertStringContainsString('scope "api" said: OZ-NOISE-OUT', $out, $out);
			self::assertStringContainsString('scope "api" said: OZ-NOISE-ERR', $out, $out);

			// Still a successful build: what the process said is reported, not turned into a failure.
			self::assertStringContainsString('settings bundles', $out, $out);
		} finally {
			self::$proj->writeFile('app/boot.php', $original);
		}
	}

	/**
	 * The statuses of the build checks of `oz doctor check`.
	 *
	 * @return array<string, string>
	 */
	private static function buildChecks(): array
	{
		$proc = self::$proj->oz('doctor', 'check', '--json');
		$proc->run();

		$out = \json_decode($proc->getOutput(), true, 512, \JSON_THROW_ON_ERROR);

		self::assertIsArray($out, $proc->getOutput() . $proc->getErrorOutput());

		return \array_intersect_key(
			\array_column($out['data']['checks'], 'status', 'name'),
			['Production build' => true, 'Preload' => true]
		);
	}

	/**
	 * Files matching a pattern under this release's build directory, where every build output goes.
	 *
	 * @return list<string>
	 */
	private static function buildFiles(string $pattern): array
	{
		$dir = InstanceLayout::releaseBuildPath(self::$proj->getPath());

		return \glob($dir . \DIRECTORY_SEPARATOR . $pattern) ?: [];
	}
}
