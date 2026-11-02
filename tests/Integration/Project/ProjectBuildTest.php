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

use Override;
use OZONE\Core\App\InstanceLayout;
use OZONE\Core\Cli\Process;
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
	private static ?OZTestProject $proj = null;

	#[Override]
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

	#[Override]
	public static function tearDownAfterClass(): void
	{
		self::$proj?->destroy();

		parent::tearDownAfterClass();
	}

	public function testBuildsWhatRequestsWouldCompile(): void
	{
		$proc = self::proj()->oz('project', 'build', '--clear', '--no-preload');
		$proc->mustRun();

		$out = $proc->getOutput();

		self::assertStringContainsString('scope "api"', $out, $out);
		self::assertNotEmpty(self::buildFiles('env/*.php'));
		self::assertNotEmpty(self::buildFiles('settings/*.php'));
		self::assertCount(1, self::buildFiles('classmap.*.php'));
		self::assertCount(1, self::buildFiles('routes/api/api.*.php'));
		self::assertFileDoesNotExist(self::proj()->getPath() . '/.ozone/preload.php');
	}

	public function testClassicModeWritesThePreloadScript(): void
	{
		self::proj()->oz('project', 'build')->mustRun();

		// what opcache.preload points to, which preloads the list of the live release
		self::assertFileExists(self::proj()->getPath() . '/.ozone/preload.php');

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
		self::proj()->oz('project', 'build', '--skip-orm', '--no-preload')->mustRun();

		$tables = self::buildFiles('routes/api/api.*.php');

		[$server, $host, $port] = self::proj()->startServer('api');

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
		self::proj()->oz('project', 'build', '--skip-orm')->mustRun();

		self::assertSame(
			['Production build' => 'ok', 'Build result' => 'ok', 'Preload' => 'ok'],
			self::buildChecks()
		);
	}

	public function testDoctorWarnsOfAStaleBuild(): void
	{
		$ns    = self::proj()->getNamespace();
		$probe = self::proj()->getPath() . '/app/StaleBuildProbe.php';

		// A class the class map does not have, and a preloaded file changed since the build.
		self::proj()->writeFile(
			'app/StaleBuildProbe.php',
			"<?php\n\ndeclare(strict_types=1);\n\nnamespace {$ns};\n\nfinal class StaleBuildProbe {}\n"
		);
		\touch(self::proj()->getPath() . '/app/TestRoutesProvider.php', \time() + 10);

		try {
			// Drift, not an incomplete build: the build itself compiled all it asked for.
			self::assertSame(
				['Production build' => 'warn', 'Build result' => 'ok', 'Preload' => 'warn'],
				self::buildChecks()
			);
		} finally {
			\unlink($probe);
		}
	}

	public function testWhatAScopeBuildPrintsBesidesItsReportIsReported(): void
	{
		$boot     = self::proj()->getPath() . '/app/boot.php';
		$original = (string) \file_get_contents($boot);

		// Each scope is built in a process of its own and its output used to be read for the report
		// and dropped. PHP puts a warning it raises while no handler is installed on one of these two.
		self::proj()->writeFile(
			'app/boot.php',
			$original . \PHP_EOL
				. "\\fwrite(\\STDOUT, 'OZ-NOISE-OUT' . \\PHP_EOL);" . \PHP_EOL
				. "\\fwrite(\\STDERR, 'OZ-NOISE-ERR' . \\PHP_EOL);" . \PHP_EOL
		);

		try {
			$proc = self::proj()->oz('project', 'build', '--skip-orm', '--no-preload');
			$proc->mustRun();

			$out = $proc->getOutput() . $proc->getErrorOutput();

			self::assertStringContainsString('scope "api" said: OZ-NOISE-OUT', $out, $out);
			self::assertStringContainsString('scope "api" said: OZ-NOISE-ERR', $out, $out);

			// Still a successful build: what the process said is reported, not turned into a failure.
			self::assertStringContainsString('settings bundles', $out, $out);
		} finally {
			self::proj()->writeFile('app/boot.php', $original);
		}
	}

	public function testASettingsSourceThatCannotBeBundledFailsTheBuild(): void
	{
		// A group holding an object: no compiled bundle can write it back, so the directory it is in
		// is read file by file instead. The build asked for a bundle of it and did not get one,
		// which used to be visible only as a count one lower than a reader had to know it should be.
		$file = 'app/settings/test.unbundlable.php';

		self::proj()->writeFile(
			$file,
			"<?php\n\ndeclare(strict_types=1);\n\nreturn ['probe' => new \\stdClass()];\n"
		);

		try {
			$proc = self::proj()->oz('project', 'build', '--clear', '--skip-orm', '--no-preload');
			$proc->run();

			$out = $proc->getOutput() . $proc->getErrorOutput();

			// A deploy script under `set -e` stops here, which is the whole point.
			self::assertNotSame(0, $proc->getExitCode(), $out);
			self::assertStringContainsString('The build is incomplete', $out, $out);

			// What was short, and why: not a bare count.
			self::assertMatchesRegularExpression('~scope "api": \d+ of \d+ settings bundles~', $out, $out);
			self::assertStringContainsString('was not bundled', $out, $out);
			self::assertStringContainsString('the group "test.unbundlable" holds an object', $out, $out);

			// With it, what the build had to work with: a reason naming a file a process could not
			// read is the same reason whether the file is unreadable or the process is out of
			// descriptors, and only one of the two can be told from the counts.
			self::assertMatchesRegularExpression('~descriptors: held \d+ at most~', $out, $out);

			// And the release carries the verdict, so the gate is a check and not a person reading
			// two numbers off a terminal.
			self::assertSame('fail', self::buildChecks()['Build result'] ?? null);
		} finally {
			\unlink(self::proj()->getPath() . \DIRECTORY_SEPARATOR . $file);
		}

		// Gone: the next build is whole again and says so.
		$proc = self::proj()->oz('project', 'build', '--clear', '--skip-orm', '--no-preload');
		$proc->mustRun();

		$out = $proc->getOutput();

		self::assertMatchesRegularExpression('~scope "api": (\d+) of \1 settings bundles~', $out, $out);
		self::assertSame('ok', self::buildChecks()['Build result'] ?? null);
	}

	public function testAFailureToReportADiagnosticIsNotReadAsTheCompileFailing(): void
	{
		// The shape the truncated builds had. A diagnostic raised while a settings directory is
		// being compiled reaches the error handler; PHP does not re-enter that handler, so a failure
		// of the handler's own is thrown from the line that raised the diagnostic -- inside the
		// compile's `catch (Throwable)`, which read it as the directory being unbundlable and
		// reported a release short of a settings bundle as a success. On the reported deploys the
		// handler failed because it had no descriptor left to autoload the logger; here it fails
		// because the configured log writer is not one, which is the same failure from the compile's
		// point of view and happens on demand.
		$logs     = 'app/settings/oz.logs.php';
		$noisy    = 'app/settings/test.noisy.php';
		$log_file = self::proj()->getPath() . \DIRECTORY_SEPARATOR . $logs;
		$before   = \is_file($log_file) ? (string) \file_get_contents($log_file) : null;

		self::proj()->setSetting('oz.logs', 'OZ_LOG_WRITER', 'OZONE\\Tests\\NoSuchLogWriter');
		self::proj()->writeFile(
			$noisy,
			"<?php\n\ndeclare(strict_types=1);\n\n"
				. "\\filemtime(__DIR__ . '/not-here.probe');\n\nreturn ['probe' => true];\n"
		);

		try {
			$proc = self::proj()->oz('project', 'build', '--clear', '--skip-orm', '--no-preload');
			$proc->run();

			$out = $proc->getOutput() . $proc->getErrorOutput();

			self::assertSame(0, $proc->getExitCode(), $out);

			// Said where a diagnostic can still go, rather than thrown into the line that raised it.
			self::assertStringContainsString('OZone could not report an error', $out, $out);
			self::assertStringContainsString('not-here.probe', $out, $out);

			// And the compile ran to the end: every source the project has was bundled.
			self::assertMatchesRegularExpression('~scope "api": (\d+) of \1 settings bundles~', $out, $out);
		} finally {
			\unlink(self::proj()->getPath() . \DIRECTORY_SEPARATOR . $noisy);

			if (null === $before) {
				\unlink($log_file);
			} else {
				self::proj()->writeFile($logs, $before);
			}
		}
	}

	public function testTheBuildRecordsTheDescriptorsItHeldAndTheLimitItRanUnder(): void
	{
		if (!\is_readable('/proc/self/fd')) {
			self::markTestSkipped('A process counts its own descriptors through /proc, which this platform has not.');
		}

		self::proj()->oz('project', 'build', '--skip-orm', '--no-preload')->mustRun();

		$results = self::buildFiles('build.php');

		self::assertCount(1, $results);

		$result = require $results[0];

		self::assertIsArray($result);

		$descriptors = $result['scopes']['api']['descriptors'] ?? null;

		self::assertIsArray($descriptors, \var_export($result, true));

		// Measured by the process that would run out of them, and against the limit that process
		// ran under: a build that fails for want of a descriptor cannot say either afterwards, and
		// a shell asked later answers for the shell.
		self::assertIsInt($descriptors['peak']);
		self::assertGreaterThan(0, $descriptors['peak']);

		if (null !== $descriptors['limit']) {
			self::assertGreaterThan($descriptors['peak'], $descriptors['limit']);
		}
	}

	public function testAKilledScopeBuildSaysSoAndKeepsWhatItPrinted(): void
	{
		$boot     = self::proj()->getPath() . '/app/boot.php';
		$original = (string) \file_get_contents($boot);

		// A process that is killed runs nothing of OZone's afterwards -- no error handler, no shutdown
		// function, no report -- so what it printed before it died is the only account of it there can
		// be. That output is read off the pipes by `wait()`, which then throws, and it used to leave
		// with the exception: a deploy log kept the signal number from a frame naming the builder.
		//
		// Only in the scope process, which `scope_build.php` is the entry point of: the command that
		// starts it runs this boot file too, and a parent killed here would never reach the build.
		self::proj()->writeFile(
			'app/boot.php',
			$original . \PHP_EOL
				. "if (\\str_contains((string) (\$_SERVER['argv'][0] ?? ''), 'scope_build.php')) {" . \PHP_EOL
				. "\t\\fwrite(\\STDOUT, 'OZ-DYING-OUT' . \\PHP_EOL);" . \PHP_EOL
				. "\t\\fwrite(\\STDERR, 'OZ-DYING-ERR' . \\PHP_EOL);" . \PHP_EOL
				// Flushed on purpose: what is still in a buffer dies with the process, and a build that
				// is killed mid-sentence is exactly the case that has to report what it managed to say.
				. "\t\\fflush(\\STDOUT);" . \PHP_EOL
				// 9 is SIGKILL, which cannot be caught: the process says nothing of its own, as the
				// reported segfault said nothing. The constant is ext-pcntl's, which OZone does not take.
				. "\t\\posix_kill(\\posix_getpid(), 9);" . \PHP_EOL
				. '}' . \PHP_EOL
		);

		try {
			$proc = self::proj()->oz('project', 'build', '--skip-orm', '--no-preload');
			$proc->run();

			$out = $proc->getOutput() . $proc->getErrorOutput();

			// A deploy under `set -e` stops here, as it does on a build that comes out short.
			self::assertNotSame(0, $proc->getExitCode(), $out);

			// The signal, by number and by name, and what it means for the process: "signaled with
			// signal 11" told the one deploy that read it nothing it could act on.
			self::assertStringContainsString('was killed by signal 9 (SIGKILL', $out, $out);

			// And both streams of the dead process.
			self::assertStringContainsString('OZ-DYING-OUT', $out, $out);
			self::assertStringContainsString('OZ-DYING-ERR', $out, $out);
		} finally {
			self::proj()->writeFile('app/boot.php', $original);
		}
	}

	public function testDoctorFailsOnAProductionReleaseWithNoBuild(): void
	{
		self::proj()->oz('project', 'build', '--skip-orm', '--no-preload')->mustRun();

		$built = [...self::buildFiles('classmap.*.php'), ...self::buildFiles('build.php')];

		self::assertCount(2, $built);

		// What a build that was killed leaves behind: a release with nothing compiled in it. Nothing
		// is short, because nothing was written, so the check for a short build has nothing to read --
		// and this used to be two warnings and an exit 0, which is a gate passing the one release it
		// exists to stop.
		foreach ($built as $file) {
			\unlink($file);
		}

		$proc = self::proj()->oz('doctor', 'check');
		$proc->run();

		$out    = $proc->getOutput() . $proc->getErrorOutput();
		$checks = self::buildChecks();

		self::assertNotSame(0, $proc->getExitCode(), $out);
		self::assertSame('fail', $checks['Production build'] ?? null, $out);

		// Said rather than left out, and a warning: a release built by an older OZone has no result
		// file either, and the row above is what fails for one of this OZone.
		self::assertSame('warn', $checks['Build result'] ?? null, $out);

		// Left as the other tests of this class expect to find it.
		self::proj()->oz('project', 'build', '--skip-orm', '--no-preload')->mustRun();

		self::assertSame('ok', self::buildChecks()['Build result'] ?? null);
	}

	public function testASettingsFileMayReadAnotherGroupThroughARegistry(): void
	{
		$ns = self::proj()->getNamespace();

		// What a project writes: `oz.routes.api` returns a registry's services, and the registry
		// reads the group that says which modules are enabled. The build runs as a request would
		// (a worker runtime, so settings come from bundles), and this read lands inside the compile
		// of the very directory it reads from -- where no memo is in place yet, because none of them
		// is written before the work. It was unbounded: one directory handle per turn until the
		// process reached its descriptor limit, then heap until the host killed it.
		self::proj()->writeFile('app/TestRegistry.php', \sprintf(
			"<?php\n\ndeclare(strict_types=1);\n\nnamespace %s;\n\n"
				. "use OZONE\\Core\\App\\Settings;\n\n"
				. "final class TestRegistry\n{\n"
				. "\tprivate static ?array \$values = null;\n\n"
				. "\tpublic static function values(): array\n\t{\n"
				. "\t\t// Memoised after the read returns, as a registry is: the third memo in the\n"
				. "\t\t// chain that was not in place during it.\n"
				. "\t\tif (null === self::\$values) {\n"
				. "\t\t\tself::\$values = Settings::load('test.modules');\n"
				. "\t\t}\n\n"
				. "\t\treturn self::\$values;\n\t}\n}\n",
			$ns
		));
		self::proj()->writeFile(
			'app/settings/test.registry.php',
			\sprintf("<?php\n\ndeclare(strict_types=1);\n\nreturn \\%s\\TestRegistry::values();\n", $ns)
		);
		self::proj()->writeFile(
			'app/settings/test.modules.php',
			"<?php\n\ndeclare(strict_types=1);\n\nreturn ['probe' => true];\n"
		);

		try {
			// The scope process on its own, under a memory bound: a return of the loop fails here in
			// seconds with a fatal naming Settings.php, where `oz project build` would hang until
			// the machine gave out.
			$proc = new Process(
				[
					\PHP_BINARY,
					'-d',
					'memory_limit=256M',
					\dirname(__DIR__, 3) . '/oz/Cli/Build/scope_build.php',
					'api',
				],
				self::proj()->getPath()
			);

			$proc->run();

			$out = $proc->getOutput() . $proc->getErrorOutput();

			self::assertSame(0, $proc->getExitCode(), $out);

			$lines  = \preg_split('~\R~', \trim($proc->getOutput())) ?: [];
			$report = \json_decode((string) \array_last($lines), true, 512, \JSON_THROW_ON_ERROR);

			self::assertIsArray($report, $out);
			self::assertTrue($report['production'], $out);

			// Every source bundled, the registry's group among them.
			self::assertSame($report['settings_sources'], $report['settings_bundles'], $out);

			// And the count that says the loop is gone: a whole build holds a handful, the one that
			// was killed held 65 530, every one of them the same settings directory.
			self::assertLessThan(100, $report['descriptors']['peak'], $out);
		} finally {
			foreach (['app/TestRegistry.php', 'app/settings/test.registry.php', 'app/settings/test.modules.php'] as $file) {
				\unlink(self::proj()->getPath() . \DIRECTORY_SEPARATOR . $file);
			}
		}

		// Left as the other tests of this class expect to find it.
		self::proj()->oz('project', 'build', '--clear', '--skip-orm', '--no-preload')->mustRun();
	}

	/**
	 * The project this class runs against, which its own setup created.
	 *
	 * Empty until that setup has run, and a test reaching it before then says so here
	 * rather than as a call on null several frames away.
	 */
	private static function proj(): OZTestProject
	{
		$project = self::$proj;

		self::assertNotNull($project, 'The test project was not created.');

		return $project;
	}

	/**
	 * The statuses of the build checks of `oz doctor check`.
	 *
	 * @return array<string, string>
	 */
	private static function buildChecks(): array
	{
		$proc = self::proj()->oz('doctor', 'check', '--json');
		$proc->run();

		$out = \json_decode($proc->getOutput(), true, 512, \JSON_THROW_ON_ERROR);

		self::assertIsArray($out, $proc->getOutput() . $proc->getErrorOutput());

		return \array_intersect_key(
			\array_column($out['data']['checks'], 'status', 'name'),
			['Production build' => true, 'Build result' => true, 'Preload' => true]
		);
	}

	/**
	 * Files matching a pattern under this release's build directory, where every build output goes.
	 *
	 * @return list<string>
	 */
	private static function buildFiles(string $pattern): array
	{
		$dir = InstanceLayout::releaseBuildPath(self::proj()->getPath());

		return \glob($dir . \DIRECTORY_SEPARATOR . $pattern) ?: [];
	}
}
