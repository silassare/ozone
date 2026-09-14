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

use OZONE\Core\Scopes\StateLayout;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OZONE\Core\Scopes\StateLayout
 *
 * @internal
 */
final class StateLayoutPathTest extends TestCase
{
	public function testThePathIsTheDirectory(): void
	{
		$app  = app();
		$path = StateLayout::path($app, StateLayout::SETTINGS);

		self::assertSame(StateLayout::dir($app, StateLayout::SETTINGS)->getRoot(), $path);
		self::assertDirectoryExists($path);

		// Kept: the same path, not another FilesManager.
		self::assertSame($path, StateLayout::path($app, StateLayout::SETTINGS));
		self::assertNotSame($path, StateLayout::path($app, StateLayout::STATE));
	}
}
