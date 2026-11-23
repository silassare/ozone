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

namespace OZONE\Tests\App;

use InvalidArgumentException;
use OZONE\Core\App\Settings;
use OZONE\Core\App\SubScope;
use OZONE\Core\Scopes\Interfaces\ScopeInterface;
use OZONE\Core\Scopes\StateLayout;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class SubScopeTest.
 *
 * A scope is refused at construction, before its settings directory becomes a settings source:
 * a name the layout already owns would have the scope read and write someone else's state.
 *
 * @internal
 */
#[CoversClass(SubScope::class)]
final class SubScopeTest extends TestCase
{
	public function testTheApplicationsOwnSlugIsRefused(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('~reserved~');

		new SubScope(ScopeInterface::ROOT_SCOPE);
	}

	public function testThePluginsDirectoryIsRefused(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessageMatches('~reserved~');

		new SubScope(StateLayout::PLUGINS);
	}

	public function testANameThatWouldLeaveTheProjectIsRefused(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new SubScope('..');
	}

	public function testANameThatIsNotASlugIsRefused(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new SubScope('My Scope');
	}

	/**
	 * Naming another scope of the project, as a command or a call into that scope does, must leave
	 * the settings of the scope being served as they are: its stateful settings used to become a
	 * source of this process, the last one, so they won over the served scope's own.
	 */
	public function testNamingAnotherScopeAddsNoSettingsSource(): void
	{
		$name = 'named-' . \bin2hex(\random_bytes(4));
		$dir  = app()->getProjectDir()->resolve('scopes' . \DIRECTORY_SEPARATOR . $name);

		\mkdir($dir, 0o775, true);

		try {
			$before = Settings::getSources()->getAllSources();
			$scope  = app()->getScope($name);

			self::assertSame($name, $scope->getName());
			self::assertSame($before, Settings::getSources()->getAllSources());
		} finally {
			// The sandbox is the suite's: what lists its scopes must not find this one.
			\rmdir($dir);
		}
	}
}
