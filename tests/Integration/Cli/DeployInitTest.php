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

namespace OZONE\Tests\Integration\Cli;

use OZONE\Tests\Integration\Support\OZTestProject;
use PHPUnit\Framework\TestCase;

/**
 * Verifies `oz deploy init` in a real project: what it generates, that the content is derived from
 * the project rather than hardcoded, and that it never silently replaces a file an operator has
 * edited.
 *
 * @internal
 *
 * @coversNothing
 */
final class DeployInitTest extends TestCase
{
	private const SCOPE  = 'myweb';
	private const ORIGIN = 'web.example.com';

	private static OZTestProject $proj;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		self::$proj = OZTestProject::create('deploy-init-test');
		self::$proj->oz('scopes', 'add', '--name=' . self::SCOPE, '--origin=http://' . self::ORIGIN, '--api=false')
			->mustRun();
	}

	public static function tearDownAfterClass(): void
	{
		self::$proj->destroy();
		parent::tearDownAfterClass();
	}

	public function testDryRunWritesNothing(): void
	{
		$proc = self::$proj->oz('deploy', 'init', '--dry-run');
		$proc->mustRun();

		self::assertStringContainsString('docker/compose.yaml', $proc->getOutput());
		self::assertDirectoryDoesNotExist(self::path('docker'));
	}

	public function testDockerTargetIsTheSampleOfThePackage(): void
	{
		self::$proj->oz('deploy', 'init', '--target=docker')->mustRun();

		foreach (['Dockerfile', 'compose.yaml', 'docker-entrypoint.sh', 'php.ini'] as $name) {
			self::assertFileExists(self::path('docker/' . $name));
		}

		self::assertTrue(\is_executable(self::path('docker/docker-entrypoint.sh')), 'entrypoint must be executable');

		$compose    = (string) \file_get_contents(self::path('docker/compose.yaml'));
		$entrypoint = (string) \file_get_contents(self::path('docker/docker-entrypoint.sh'));

		// One volume for every state directory: public/*/static are symlinks into data/static now.
		self::assertStringContainsString('data:/var/www/html/data', $compose);
		self::assertStringNotContainsString('public-static', $compose);

		// The links are not in the image, so each container makes them before anything else.
		self::assertStringContainsString('oz project link', $entrypoint);
	}

	public function testBareTargetDerivesTheVhostFromTheScopeOrigin(): void
	{
		self::$proj->oz('deploy', 'init', '--target=bare', '--user=deploy', '--web-user=nginx')->mustRun();

		// The file is named after the scope's own OZ_DEFAULT_ORIGIN, not after the running scope.
		$vhost_path = self::path('deploy/nginx/' . self::ORIGIN . '.conf');

		self::assertFileExists($vhost_path);

		$vhost = (string) \file_get_contents($vhost_path);

		self::assertStringContainsString('server_name ' . self::ORIGIN . ';', $vhost);
		self::assertStringContainsString('root ' . self::path('public/' . self::SCOPE) . ';', $vhost);

		// The public files are behind a symlink that leaves the document root.
		self::assertStringContainsString('disable_symlinks off;', $vhost);

		// No token survived into a file a web server would read.
		self::assertDoesNotMatchRegularExpression('~__[A-Z][A-Z0-9_]*__~', $vhost);
	}

	public function testBarePoolCarriesTheScopeAndTheRequestedUser(): void
	{
		$pool = (string) \file_get_contents(self::poolPath(self::SCOPE));

		self::assertStringContainsString('env[OZ_SCOPE_NAME] = ' . self::SCOPE, $pool);
		self::assertStringContainsString('user = deploy', $pool);
		self::assertStringContainsString('listen.owner = nginx', $pool);
		self::assertStringContainsString('open_basedir] = ' . \rtrim(self::path(''), \DIRECTORY_SEPARATOR), $pool);
		self::assertDoesNotMatchRegularExpression('~__[A-Z][A-Z0-9_]*__~', $pool);
	}

	public function testBareGeneratesForEveryScopeIncludingTheRootOne(): void
	{
		self::assertFileExists(self::poolPath('root'));
		self::assertFileExists(self::poolPath(self::SCOPE));
	}

	public function testBareSystemdUnitsAreRealUnitFilesPointingAtTheProject(): void
	{
		// Named as systemd expects, so they are copied rather than transcribed out of a text file.
		$cron   = (string) \file_get_contents(self::path('deploy/systemd/deploy-init-test-cron.service'));
		$timer  = (string) \file_get_contents(self::path('deploy/systemd/deploy-init-test-cron.timer'));
		$worker = (string) \file_get_contents(self::path('deploy/systemd/deploy-init-test-worker@.service'));

		foreach ([$cron, $timer, $worker] as $unit) {
			self::assertDoesNotMatchRegularExpression('~__[A-Z][A-Z0-9_]*__~', $unit);
		}

		foreach ([$cron, $worker] as $unit) {
			self::assertStringContainsString('WorkingDirectory=' . \rtrim(self::path(''), \DIRECTORY_SEPARATOR), $unit);
			self::assertStringContainsString('User=deploy', $unit);
		}

		self::assertStringContainsString('cron run', $cron);
		self::assertStringContainsString('Unit=deploy-init-test-cron.service', $timer);
		self::assertStringContainsString('jobs work', $worker);
		self::assertStringContainsString('%i', $worker, 'the worker unit is a template unit');
	}

	public function testBareTurnsPreloadingOnAsThePoolUser(): void
	{
		$ini = (string) \file_get_contents(self::path('deploy/php/deploy-init-test-preload.ini'));

		self::assertStringContainsString('opcache.preload = ' . self::path('.ozone/preload.php') . "\n", $ini);
		self::assertStringContainsString('opcache.preload_user = deploy' . "\n", $ini);
		self::assertDoesNotMatchRegularExpression('~__[A-Z][A-Z0-9_]*__~', $ini);
	}

	public function testCiWorkflowIsGeneratedOnlyWhenAsked(): void
	{
		self::assertFileDoesNotExist(self::path('.github/workflows/ci.yml'));

		self::$proj->oz('deploy', 'init', '--target=docker', '--ci=github', '--branch=release')->mustRun();

		$workflow = (string) \file_get_contents(self::path('.github/workflows/ci.yml'));

		self::assertStringContainsString('branches: [release]', $workflow);
		self::assertStringContainsString('${{ secrets.DEPLOY_HOST }}', $workflow, 'GitHub expressions must survive');
		self::assertStringContainsString('oz doctor check', $workflow);
		self::assertDoesNotMatchRegularExpression('~__[A-Z][A-Z0-9_]*__~', $workflow);
	}

	public function testAnEditedFileIsKeptUnlessForced(): void
	{
		$path = self::path('docker/php.ini');

		\file_put_contents($path, "; edited by the operator\n");

		$proc = self::$proj->oz('deploy', 'init', '--target=docker');
		$proc->mustRun();

		self::assertSame("; edited by the operator\n", \file_get_contents($path));
		self::assertStringContainsString('already there, kept', $proc->getOutput());

		self::$proj->oz('deploy', 'init', '--target=docker', '--force')->mustRun();

		self::assertNotSame("; edited by the operator\n", \file_get_contents($path));
	}

	public function testAnUnknownTargetIsRefused(): void
	{
		$proc = self::$proj->oz('deploy', 'init', '--target=kubernetes');
		$proc->run();

		self::assertNotSame(0, $proc->getExitCode());
		self::assertStringContainsString(
			'kubernetes',
			(string) \preg_replace('~\s+~', ' ', $proc->getOutput() . $proc->getErrorOutput())
		);
	}

	private static function poolPath(string $scope): string
	{
		return self::path('deploy/php-fpm/deploy-init-test-' . $scope . '.conf');
	}

	private static function path(string $relative): string
	{
		$path = self::$proj->getPath();

		return '' === $relative
			? $path
			: $path . \DIRECTORY_SEPARATOR . \str_replace('/', \DIRECTORY_SEPARATOR, $relative);
	}
}
