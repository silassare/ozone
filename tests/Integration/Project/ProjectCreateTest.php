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

use OZONE\Tests\Integration\Support\OZTestProject;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that `oz project create` scaffolds the expected file/directory
 * structure and that the generated files are syntactically valid PHP.
 *
 * One project is created for the whole class (setUpBeforeClass) and
 * destroyed afterwards (tearDownAfterClass).
 *
 * @internal
 *
 * @coversNothing
 */
final class ProjectCreateTest extends TestCase
{
	private static OZTestProject $proj;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		self::$proj = OZTestProject::create('project-create-test');
	}

	public static function tearDownAfterClass(): void
	{
		self::$proj->destroy();
		parent::tearDownAfterClass();
	}

	// -------------------------------------------------------------------------
	// Core app directory
	// -------------------------------------------------------------------------

	public function testAppDirectoryExists(): void
	{
		self::assertDirectoryExists(self::path('app'));
	}

	public function testAppSettingsDirectoryExists(): void
	{
		self::assertDirectoryExists(self::path('app/settings'));
	}

	public function testAppTemplatesDirectoryExists(): void
	{
		self::assertDirectoryExists(self::path('app/templates'));
	}

	public function testAppFilesDirectoryExists(): void
	{
		self::assertDirectoryExists(self::path('app/files'));
	}

	public function testAppBootExists(): void
	{
		self::assertFileExists(self::path('app/boot.php'));
	}

	public function testAppInstanceExists(): void
	{
		self::assertFileExists(self::path('app/app.php'));
	}

	public function testAppClassFileExists(): void
	{
		// Default class name is SampleApp so the file is SampleApp.php
		self::assertFileExists(self::path('app/SampleApp.php'));
	}

	public function testAppHtaccessDeniesAll(): void
	{
		self::assertFileExists(self::path('app/.htaccess'));
		self::assertSame('deny from all', \file_get_contents(self::path('app/.htaccess')));
	}

	// -------------------------------------------------------------------------
	// Settings files
	// -------------------------------------------------------------------------

	public function testOzConfigSettingsExists(): void
	{
		self::assertFileExists(self::path('app/settings/oz.config.php'));
	}

	public function testOzConfigContainsProjectName(): void
	{
		$config = require self::path('app/settings/oz.config.php');
		self::assertIsArray($config);
		self::assertSame('project-create-test', $config['OZ_PROJECT_NAME']);
	}

	public function testOzConfigContainsNamespace(): void
	{
		$config = require self::path('app/settings/oz.config.php');
		self::assertArrayHasKey('OZ_PROJECT_NAMESPACE', $config);
		self::assertNotEmpty($config['OZ_PROJECT_NAMESPACE']);
	}

	public function testOzConfigContainsPrefix(): void
	{
		$config = require self::path('app/settings/oz.config.php');
		self::assertArrayHasKey('OZ_PROJECT_PREFIX', $config);
		self::assertMatchesRegularExpression('/^[A-Z]{2}$/', $config['OZ_PROJECT_PREFIX']);
	}

	public function testOzConfigContainsAppClassName(): void
	{
		$config = require self::path('app/settings/oz.config.php');
		self::assertArrayHasKey('OZ_PROJECT_APP_CLASS_NAME', $config);
	}

	public function testOzRequestSettingsExists(): void
	{
		self::assertFileExists(self::path('app/settings/oz.request.php'));
	}

	public function testOzDbSettingsExists(): void
	{
		self::assertFileExists(self::path('app/settings/oz.db.php'));
	}

	// -------------------------------------------------------------------------
	// Project root files
	// -------------------------------------------------------------------------

	public function testDotEnvExists(): void
	{
		self::assertFileExists(self::path('.env'));
	}

	public function testDotEnvExampleExists(): void
	{
		self::assertFileExists(self::path('.env.example'));
	}

	public function testDotEnvContainsSalt(): void
	{
		$env = \file_get_contents(self::path('.env'));
		self::assertStringContainsString('OZ_APP_SALT=', $env);
	}

	public function testDotEnvContainsSecret(): void
	{
		$env = \file_get_contents(self::path('.env'));
		self::assertStringContainsString('OZ_APP_SECRET=', $env);
	}

	public function testGitignoreExists(): void
	{
		self::assertFileExists(self::path('.gitignore'));
	}

	public function testComposerJsonExists(): void
	{
		self::assertFileExists(self::path('composer.json'));
	}

	public function testComposerJsonIsValidJson(): void
	{
		$content = \file_get_contents(self::path('composer.json'));
		$decoded = \json_decode($content, true);
		self::assertIsArray($decoded);
		self::assertArrayHasKey('require', $decoded);
	}

	public function testComposerJsonRequiresOzone(): void
	{
		$composer = \json_decode(\file_get_contents(self::path('composer.json')), true);
		self::assertArrayHasKey('silassare/ozone', $composer['require']);
	}

	// -------------------------------------------------------------------------
	// Default api scope (auto-created by project create)
	// -------------------------------------------------------------------------

	public function testApiScopePrivateDirectoryExists(): void
	{
		self::assertDirectoryExists(self::path('scopes/api'));
	}

	public function testApiScopeSettingsDirectoryExists(): void
	{
		self::assertDirectoryExists(self::path('scopes/api/settings'));
	}

	public function testApiScopeRequestSettingsExists(): void
	{
		self::assertFileExists(self::path('scopes/api/settings/oz.request.php'));
	}

	public function testApiScopeTemplatesDirectoryExists(): void
	{
		self::assertDirectoryExists(self::path('scopes/api/templates'));
	}

	public function testApiScopePrivateHtaccessExists(): void
	{
		self::assertFileExists(self::path('scopes/api/.htaccess'));
		self::assertSame('deny from all', \file_get_contents(self::path('scopes/api/.htaccess')));
	}

	public function testApiScopePublicDirectoryExists(): void
	{
		self::assertDirectoryExists(self::path('public/api'));
	}

	public function testApiScopeIndexPhpExists(): void
	{
		self::assertFileExists(self::path('public/api/index.php'));
	}

	public function testApiScopeIndexPhpContainsScopeName(): void
	{
		$content = \file_get_contents(self::path('public/api/index.php'));
		self::assertStringContainsString("'api'", $content);
	}

	public function testApiScopeRobotsTxtExists(): void
	{
		self::assertFileExists(self::path('public/api/robots.txt'));
	}

	public function testApiScopeFaviconExists(): void
	{
		self::assertFileExists(self::path('public/api/favicon.ico'));
	}

	public function testApiScopePublicHtaccessExists(): void
	{
		self::assertFileExists(self::path('public/api/.htaccess'));
	}

	// -------------------------------------------------------------------------
	// PHP syntax validity for generated PHP files
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider provideGeneratedPhpFileIsSyntacticallyValidCases
	 */
	public function testGeneratedPhpFileIsSyntacticallyValid(string $relative): void
	{
		$path   = self::path($relative);
		$output = [];
		$code   = 0;
		\exec(\PHP_BINARY . ' -l ' . \escapeshellarg($path) . ' 2>&1', $output, $code);
		self::assertSame(0, $code, \implode("\n", $output));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function provideGeneratedPhpFileIsSyntacticallyValidCases(): iterable
	{
		return [
			'app/boot.php'                       => ['app/boot.php'],
			'app/app.php'                        => ['app/app.php'],
			'app/SampleApp.php'                  => ['app/SampleApp.php'],
			'app/settings/oz.config.php'         => ['app/settings/oz.config.php'],
			'app/settings/oz.request.php'        => ['app/settings/oz.request.php'],
			'app/settings/oz.db.php'             => ['app/settings/oz.db.php'],
			'scopes/api/settings/oz.request.php' => ['scopes/api/settings/oz.request.php'],
			'public/api/index.php'               => ['public/api/index.php'],
		];
	}

	// -------------------------------------------------------------------------
	// Idempotency: running create again on the same directory must be a no-op
	// -------------------------------------------------------------------------

	public function testProjectCreateIsIdempotent(): void
	{
		$proc = self::$proj->oz(
			'project',
			'create',
			'--root-dir=' . self::$proj->getPath(),
			'--name=project-create-test'
		);
		$proc->run();
		// The command should exit 0 but print an error about existing project.
		// The important thing is it does NOT wipe the existing files.
		self::assertFileExists(self::path('app/boot.php'));
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	private static function path(string $relative): string
	{
		return self::$proj->getPath() . \DIRECTORY_SEPARATOR . \str_replace('/', \DIRECTORY_SEPARATOR, $relative);
	}
}
