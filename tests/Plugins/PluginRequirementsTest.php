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

use Override;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Plugins\AbstractPlugin;
use OZONE\Core\Plugins\Interfaces\PluginInterface;
use OZONE\Core\Plugins\Plugins;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class PluginRequirementsTest.
 *
 * What a plugin requires is what its package requires through Composer, among the packages of the
 * plugins a project lists: those boot first, and one left disabled, or a cycle, is refused by name.
 *
 * @internal
 */
#[CoversClass(Plugins::class)]
#[CoversClass(AbstractPlugin::class)]
final class PluginRequirementsTest extends TestCase
{
	/** @var list<string> */
	private static array $dirs = [];

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		AlphaPlugin::$root = self::package('acme/alpha', []);
		// What a plugin's package requires beyond plugins -- PHP, a library -- is Composer's alone.
		BetaPlugin::$root  = self::package('acme/beta', ['php' => '>=8.4', 'acme/alpha' => '^1.0', 'psr/log' => '*']);
		GammaPlugin::$root = self::package('acme/gamma', ['acme/delta' => '*']);
		DeltaPlugin::$root = self::package('acme/delta', ['acme/gamma' => '*']);
	}

	#[Override]
	public static function tearDownAfterClass(): void
	{
		foreach (self::$dirs as $dir) {
			\unlink($dir . '/composer.json');
			\rmdir($dir);
		}

		parent::tearDownAfterClass();
	}

	public function testAPluginReadsWhatItsPackageRequires(): void
	{
		self::assertSame(['php', 'acme/alpha', 'psr/log'], BetaPlugin::instance()->getRequiredPackages());
		self::assertSame([], AlphaPlugin::instance()->getRequiredPackages());
	}

	public function testWhatAPluginRequiresBootsBeforeIt(): void
	{
		// Listed in the other order: the project's list says what is enabled, not the order.
		$order = Plugins::bootOrder([BetaPlugin::class => true, AlphaPlugin::class => true]);

		self::assertSame([AlphaPlugin::class, BetaPlugin::class], self::classes($order));
	}

	public function testAPluginWhoseRequiredPluginIsDisabledIsRefusedByName(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains(\sprintf(
			'Plugin "%s" requires plugin "%s" (acme/alpha), which is disabled.',
			BetaPlugin::class,
			AlphaPlugin::class
		));

		Plugins::bootOrder([BetaPlugin::class => true, AlphaPlugin::class => false]);
	}

	public function testADisabledPluginRequiresNothing(): void
	{
		self::assertSame(
			[AlphaPlugin::class],
			self::classes(Plugins::bootOrder([BetaPlugin::class => false, AlphaPlugin::class => true]))
		);
	}

	public function testPluginsRequiringEachOtherAreRefusedNamingTheCycle(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains(\sprintf(
			'Plugins require each other: %s -> %s -> %s.',
			GammaPlugin::class,
			DeltaPlugin::class,
			GammaPlugin::class
		));

		Plugins::bootOrder([GammaPlugin::class => true, DeltaPlugin::class => true]);
	}

	/**
	 * A directory holding a package's composer.json.
	 *
	 * @param array<string, string> $require
	 */
	private static function package(string $name, array $require): string
	{
		$dir = \sys_get_temp_dir() . '/oz_plugin_req_' . \bin2hex(\random_bytes(6));

		\mkdir($dir, 0o775, true);
		\file_put_contents($dir . '/composer.json', \json_encode(['name' => $name, 'require' => $require]));

		self::$dirs[] = $dir;

		return $dir;
	}

	/**
	 * @param list<PluginInterface> $plugins
	 *
	 * @return list<class-string<PluginInterface>>
	 */
	private static function classes(array $plugins): array
	{
		return \array_map(static fn(PluginInterface $plugin): string => $plugin::class, $plugins);
	}
}

/**
 * A plugin requiring no other.
 *
 * @internal
 */
final class AlphaPlugin extends AbstractPlugin
{
	public static string $root = '';

	public function __construct()
	{
		parent::__construct('alpha', 'Acme\Alpha', self::$root);
	}

	#[Override]
	public static function instance(): static
	{
		return new self();
	}
}

/**
 * A plugin whose package requires alpha's.
 *
 * @internal
 */
final class BetaPlugin extends AbstractPlugin
{
	public static string $root = '';

	public function __construct()
	{
		parent::__construct('beta', 'Acme\Beta', self::$root);
	}

	#[Override]
	public static function instance(): static
	{
		return new self();
	}
}

/**
 * One of two plugins whose packages require each other.
 *
 * @internal
 */
final class GammaPlugin extends AbstractPlugin
{
	public static string $root = '';

	public function __construct()
	{
		parent::__construct('gamma', 'Acme\Gamma', self::$root);
	}

	#[Override]
	public static function instance(): static
	{
		return new self();
	}
}

/**
 * The other one.
 *
 * @internal
 */
final class DeltaPlugin extends AbstractPlugin
{
	public static string $root = '';

	public function __construct()
	{
		parent::__construct('delta', 'Acme\Delta', self::$root);
	}

	#[Override]
	public static function instance(): static
	{
		return new self();
	}
}
