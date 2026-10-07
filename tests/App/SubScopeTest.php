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
use OZONE\Core\App\SubScope;
use OZONE\Core\Scopes\Interfaces\ScopeInterface;
use OZONE\Core\Scopes\StateLayout;
use PHPUnit\Framework\TestCase;

/**
 * Class SubScopeTest.
 *
 * A scope is refused at construction, before its settings directory becomes a settings source:
 * a name the layout already owns would have the scope read and write someone else's state.
 *
 * @internal
 *
 * @covers \OZONE\Core\App\SubScope
 */
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
}
