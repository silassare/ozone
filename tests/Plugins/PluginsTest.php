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

namespace OZONE\Tests\Plugins;

use Exception;
use Override;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Loader\ClassLoader;
use OZONE\Core\Plugins\AbstractPlugin;
use OZONE\Core\Plugins\CorePlugin;
use OZONE\Core\Plugins\Plugins;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Class PluginsTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Plugins\AbstractPlugin
 * @covers \OZONE\Core\Plugins\Plugins
 */
final class PluginsTest extends TestCase
{
	private static string $dir;

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		self::$dir = \sys_get_temp_dir() . '/oz_plugin_' . \bin2hex(\random_bytes(6));

		\mkdir(self::$dir, 0o775, true);
		\file_put_contents(self::$dir . '/composer.json', \json_encode([
			'name'        => 'acme/stub-plugin',
			'description' => 'A stub plugin.',
			'version'     => '1.2.3',
			'authors'     => [['name' => 'Jane Doe']],
		]));
	}

	#[Override]
	public static function tearDownAfterClass(): void
	{
		\unlink(self::$dir . '/composer.json');
		\rmdir(self::$dir);

		parent::tearDownAfterClass();
	}

	public function testCorePluginDescribesOZone(): void
	{
		$plugin = Plugins::ozone();

		self::assertInstanceOf(CorePlugin::class, $plugin);
		self::assertSame('ozone', $plugin->getName());
		self::assertSame('OZONE\Core', $plugin->getNamespace());
		self::assertSame('OZONE\Core\Db', $plugin->getDbNamespace());
		self::assertSame('silassare/ozone', $plugin->getPackageName());
		self::assertSame(\realpath(OZ_OZONE_DIR . '..'), \realpath($plugin->getInstallPath()));
	}

	public function testPluginsAreInstantiatedOnce(): void
	{
		self::assertSame(Plugins::ozone(), Plugins::getPlugin(CorePlugin::class));
	}

	public function testEachPluginHasOneScope(): void
	{
		$plugin = Plugins::ozone();
		$scope  = Plugins::scopeOf($plugin);

		self::assertSame($scope, $plugin->getScope());
		self::assertSame('ozone', $scope->getName());

		// A plugin's state lives under data/{kind}/plugins/{plugin}; getDataDir() is the one data
		// root shared by every scope.
		self::assertSame('plugins/ozone', \str_replace('\\', '/', $scope->getStateSlug()));
		self::assertStringEndsWith(
			'/data/settings/plugins/ozone',
			\rtrim(\str_replace('\\', '/', $scope->getStatefulSettingsDir()->getRoot()), '/')
		);

		// Its public assets stay inside the application's pool, so one symlink exposes them.
		self::assertStringEndsWith(
			'/data/static/root/plugins/ozone',
			\rtrim(\str_replace('\\', '/', $scope->getPublicFilesDir()->getRoot()), '/')
		);
	}

	public function testGetPluginRejectsAClassThatIsNotAPlugin(): void
	{
		$this->expectException(RuntimeException::class);

		Plugins::getPlugin(stdClass::class);
	}

	public function testAbstractPluginReadsItsComposerJson(): void
	{
		StubPlugin::$root = self::$dir;

		$plugin = StubPlugin::instance();

		self::assertSame('stub-plugin', $plugin->getName());
		self::assertSame('acme/stub-plugin', $plugin->getPackageName());
		self::assertSame('A stub plugin.', $plugin->getDescription());
		self::assertSame('Jane Doe', $plugin->getAuthor());
		self::assertSame('1.2.3', $plugin->getVersion());
		self::assertSame('Acme\StubPlugin\Db', $plugin->getDbNamespace());
	}

	public function testDisabledPluginBootsNothing(): void
	{
		StubPlugin::$root = self::$dir;

		$plugin = StubPlugin::instance();

		// Not listed in oz.plugins.
		self::assertFalse($plugin->isEnabled());

		$plugin->boot();

		self::assertArrayNotHasKey('Acme\StubPlugin\\', ClassLoader::report()['namespaces']);
	}

	public function testAbstractPluginNeedsAComposerJson(): void
	{
		StubPlugin::$root = \sys_get_temp_dir();

		$this->expectException(Exception::class);

		StubPlugin::instance();
	}
}

/**
 * A plugin installed in a test directory.
 *
 * @internal
 */
final class StubPlugin extends AbstractPlugin
{
	public static string $root = '';

	public function __construct()
	{
		parent::__construct('stub-plugin', 'Acme\StubPlugin', self::$root);
	}

	#[Override]
	public static function instance(): static
	{
		return new self();
	}
}
