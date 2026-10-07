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

namespace OZONE\Tests\Scopes;

use OZONE\Core\Scopes\Interfaces\ScopeInterface;
use OZONE\Core\Scopes\StateLayout;
use PHPUnit\Framework\TestCase;

/**
 * Class StateLayoutNamesTest.
 *
 * The first level of `data/` is one namespace, shared by the scopes and by the directory every
 * plugin's state lives under, so a name already spoken for has to be refused before anything
 * writes there: two owners of one directory read each other's settings and files.
 *
 * @internal
 *
 * @covers \OZONE\Core\Scopes\StateLayout
 */
final class StateLayoutNamesTest extends TestCase
{
	public function testTheApplicationAndThePluginsDirectoryAreReserved(): void
	{
		self::assertSame(
			[ScopeInterface::ROOT_SCOPE, StateLayout::PLUGINS],
			StateLayout::reservedScopeNames()
		);

		foreach (StateLayout::reservedScopeNames() as $name) {
			$fault = StateLayout::scopeNameFault($name);

			self::assertNotNull($fault, $name . ' must be refused as a scope name.');
			self::assertStringContainsString('reserved', $fault);
		}
	}

	public function testAScopeMayNotBeNamedAfterAStateKind(): void
	{
		// `data/settings/settings` is legal, but a name that reads as a kind is a trap for whoever
		// looks at the directory later, and `data/{scope}` would hold a directory of the same name.
		foreach (StateLayout::kinds() as $kind) {
			self::assertNotNull(
				StateLayout::scopeNameFault($kind),
				$kind . ' is a state kind and must be refused as a scope name.'
			);
		}
	}

	/**
	 * @dataProvider provideNamesThatAreNotSlugs
	 */
	public function testANameThatIsNotASlugIsRefused(string $name): void
	{
		self::assertNotNull(StateLayout::scopeNameFault($name), \var_export($name, true));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function provideNamesThatAreNotSlugs(): iterable
	{
		return [
			'empty'                 => [''],
			'this directory'        => ['.'],
			'the parent directory'  => ['..'],
			'a hidden name'         => ['.api'],
			'a path'                => ['api/v2'],
			'a windows path'        => ['api\\v2'],
			'a space'               => ['my scope'],
			'uppercase'             => ['Api'],
			'an underscore'         => ['my_scope'],
			'a leading dash'        => ['-api'],
			'a trailing dash'       => ['api-'],
			'a dot'                 => ['cron.minute'],
		];
	}

	/**
	 * @dataProvider provideSlugs
	 */
	public function testAPlainSlugIsAccepted(string $name): void
	{
		self::assertNull(StateLayout::scopeNameFault($name), $name);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function provideSlugs(): iterable
	{
		return [
			'one letter'   => ['a'],
			'a word'       => ['api'],
			'two words'    => ['admin-ui'],
			'with a digit' => ['web2'],
		];
	}

	public function testAPluginNameMustLeaveSomethingOnceSlugged(): void
	{
		// `Str::stringToURLSlug()` drops what it cannot use, so a name made only of such characters
		// leaves nothing, and the plugin's state would land in the directory every plugin shares.
		$fault = StateLayout::pluginSlugFault('');

		self::assertNotNull($fault);
		self::assertStringContainsString('every plugin', $fault);

		self::assertNull(StateLayout::pluginSlugFault('ozone'));
		self::assertNull(StateLayout::pluginSlugFault('acme-billing'));
	}
}
