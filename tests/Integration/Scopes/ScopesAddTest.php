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

namespace OZONE\Tests\Integration\Scopes;

use OZONE\Tests\Integration\Support\OZTestProject;
use PHPUnit\Framework\TestCase;

/**
 * Verifies `oz scopes add` command behaviour.
 *
 * Tests cover:
 *  - Adding an API scope
 *  - Adding a Web scope
 *  - Adding multiple scopes to the same project
 *  - Custom origin URL is persisted in oz.request settings
 *  - The generated index.php reflects the correct scope name and context
 *  - PHP syntax validity of all generated files
 *
 * One project is shared for all test methods to avoid the cost of repeated
 * composer installs.
 *
 * @internal
 *
 * @coversNothing
 */
final class ScopesAddTest extends TestCase
{
	private static OZTestProject $proj;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		self::$proj = OZTestProject::create('scopes-add-test');

		// Add an API scope
		self::$proj->oz('scopes', 'add', '--name=myapi', '--origin=http://api.example.com', '--api=true')
			->mustRun();

		// Add a Web scope
		self::$proj->oz('scopes', 'add', '--name=web', '--origin=http://www.example.com', '--api=false')
			->mustRun();

		// Add a second API scope with a custom port
		self::$proj->oz('scopes', 'add', '--name=admin', '--origin=http://admin.example.com:8080', '--api=true')
			->mustRun();
	}

	public static function tearDownAfterClass(): void
	{
		self::$proj->destroy();
		parent::tearDownAfterClass();
	}

	public function testDefaultApiScopeExists(): void
	{
		self::assertDirectoryExists(self::path('scopes/api'));
		self::assertDirectoryExists(self::path('public/api'));
	}

	public function testMyapiPrivateDirectoryExists(): void
	{
		self::assertDirectoryExists(self::path('scopes/myapi'));
	}

	public function testMyapiSettingsDirectoryExists(): void
	{
		self::assertDirectoryExists(self::path('scopes/myapi/settings'));
	}

	public function testMyapiTemplatesDirectoryExists(): void
	{
		self::assertDirectoryExists(self::path('scopes/myapi/templates'));
	}

	public function testMyapiPrivateHtaccessDeniesAll(): void
	{
		self::assertFileExists(self::path('scopes/myapi/.htaccess'));
		self::assertSame('deny from all', \file_get_contents(self::path('scopes/myapi/.htaccess')));
	}

	public function testMyapiPublicDirectoryExists(): void
	{
		self::assertDirectoryExists(self::path('public/myapi'));
	}

	public function testMyapiIndexPhpExists(): void
	{
		self::assertFileExists(self::path('public/myapi/index.php'));
	}

	public function testMyapiIndexPhpContainsScopeName(): void
	{
		$content = \file_get_contents(self::path('public/myapi/index.php'));
		self::assertStringContainsString("'myapi'", $content);
	}

	public function testMyapiRobotsAndFaviconExist(): void
	{
		self::assertFileExists(self::path('public/myapi/robots.txt'));
		self::assertFileExists(self::path('public/myapi/favicon.ico'));
	}

	public function testMyapiRequestSettingsContainsOrigin(): void
	{
		$settings = require self::path('scopes/myapi/settings/oz.request.php');
		self::assertIsArray($settings);
		self::assertSame('http://api.example.com', $settings['OZ_DEFAULT_ORIGIN']);
	}

	public function testWebScopePrivateDirectoryExists(): void
	{
		self::assertDirectoryExists(self::path('scopes/web'));
	}

	public function testWebScopePublicDirectoryExists(): void
	{
		self::assertDirectoryExists(self::path('public/web'));
	}

	public function testWebScopeIndexPhpExists(): void
	{
		self::assertFileExists(self::path('public/web/index.php'));
	}

	public function testWebScopeIndexPhpContainsScopeName(): void
	{
		$content = \file_get_contents(self::path('public/web/index.php'));
		self::assertStringContainsString("'web'", $content);
	}

	public function testWebScopeRequestSettingsContainsOrigin(): void
	{
		$settings = require self::path('scopes/web/settings/oz.request.php');
		self::assertIsArray($settings);
		self::assertSame('http://www.example.com', $settings['OZ_DEFAULT_ORIGIN']);
	}

	/**
	 * A web scope's index.php must NOT define OZ_OZONE_IS_WEB_CONTEXT to false;
	 * the template conditionally emits the define only when api=false.
	 */
	public function testWebScopeIndexPhpSetsWebContext(): void
	{
		$content = \file_get_contents(self::path('public/web/index.php'));
		self::assertStringContainsString('OZ_OZONE_IS_WEB_CONTEXT', $content);
	}

	/**
	 * An API scope's index.php must NOT set OZ_OZONE_IS_WEB_CONTEXT.
	 */
	public function testApiScopeIndexPhpDoesNotSetWebContext(): void
	{
		$content = \file_get_contents(self::path('public/myapi/index.php'));
		self::assertStringNotContainsString('OZ_OZONE_IS_WEB_CONTEXT', $content);
	}

	public function testAdminScopeExists(): void
	{
		self::assertDirectoryExists(self::path('scopes/admin'));
		self::assertDirectoryExists(self::path('public/admin'));
	}

	public function testAdminScopeRequestSettingsContainsOriginWithPort(): void
	{
		$settings = require self::path('scopes/admin/settings/oz.request.php');
		self::assertIsArray($settings);
		self::assertSame('http://admin.example.com:8080', $settings['OZ_DEFAULT_ORIGIN']);
	}

	public function testFourScopesExist(): void
	{
		$expected = ['api', 'myapi', 'web', 'admin'];
		foreach ($expected as $scope) {
			self::assertDirectoryExists(
				self::path("scopes/{$scope}"),
				"Scope '{$scope}' private dir must exist"
			);
			self::assertDirectoryExists(
				self::path("public/{$scope}"),
				"Scope '{$scope}' public dir must exist"
			);
		}
	}

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
			'scopes/myapi/settings/oz.request.php'  => ['scopes/myapi/settings/oz.request.php'],
			'scopes/web/settings/oz.request.php'    => ['scopes/web/settings/oz.request.php'],
			'scopes/admin/settings/oz.request.php'  => ['scopes/admin/settings/oz.request.php'],
			'public/myapi/index.php'                => ['public/myapi/index.php'],
			'public/web/index.php'                  => ['public/web/index.php'],
			'public/admin/index.php'                => ['public/admin/index.php'],
		];
	}

	private static function path(string $relative): string
	{
		return self::$proj->getPath() . \DIRECTORY_SEPARATOR . \str_replace('/', \DIRECTORY_SEPARATOR, $relative);
	}
}
