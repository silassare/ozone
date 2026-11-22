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

use Override;
use OZONE\Core\Plugins\CorePlugin;
use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * `oz plugins` in a real project: the plugins it lists in the order they boot, and what enabling
 * one adds, read without booting it.
 *
 * @internal
 */
#[CoversNothing]
final class PluginsCmdTest extends TestCase
{
	private static ?OZTestProject $proj = null;

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$proj = OZTestProject::create('plugins-cmd', shared: false);

		$proj->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $proj->getPath() . '/plugins_cmd_test.sqlite',
		]);

		self::$proj = $proj;
	}

	#[Override]
	public static function tearDownAfterClass(): void
	{
		self::$proj?->destroy();
		self::$proj = null;

		parent::tearDownAfterClass();
	}

	public function testListsThePluginsInTheOrderTheyBoot(): void
	{
		$plugins = self::json('list')['plugins'] ?? null;

		self::assertIsArray($plugins);
		self::assertSame(CorePlugin::class, $plugins[0]['class'] ?? null);
		self::assertSame('silassare/ozone', $plugins[0]['package'] ?? null);
		self::assertTrue($plugins[0]['enabled'] ?? null);
	}

	public function testDescribesAPluginByItsPackage(): void
	{
		$described = self::json('describe', '--plugin=silassare/ozone');

		self::assertSame(CorePlugin::class, $described['class'] ?? null);
		self::assertContains('oz_users', $described['tables'] ?? []);
		self::assertArrayHasKey('oz.routes.api', $described['settings'] ?? []);
	}

	public function testRefusesAPluginTheProjectDoesNotList(): void
	{
		$proc = self::project()->oz('plugins', 'describe', '--plugin=acme/nowhere');
		$proc->run();

		self::assertNotSame(0, $proc->getExitCode());
		self::assertStringContainsString(
			'No plugin "acme/nowhere" in oz.plugins',
			$proc->getErrorOutput() . $proc->getOutput()
		);
	}

	/**
	 * What `oz plugins <action> --json` answers, its `data`.
	 *
	 * @return array<string, mixed>
	 */
	private static function json(string $action, string ...$args): array
	{
		$proc = self::project()->oz('plugins', $action, ...$args, ...['--json']);
		$proc->mustRun();

		$out = \json_decode($proc->getOutput(), true);

		self::assertIsArray($out);
		self::assertIsArray($out['data'] ?? null);

		return $out['data'];
	}

	private static function project(): OZTestProject
	{
		$project = self::$proj;

		self::assertNotNull($project, 'The test project was not created.');

		return $project;
	}
}
