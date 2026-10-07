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

	public function testTheScopeComesBeforeTheKind(): void
	{
		$app  = app();
		$slug = $app->getStateSlug();
		$ds   = \DIRECTORY_SEPARATOR;

		foreach (StateLayout::kinds() as $kind) {
			self::assertStringEndsWith(
				$ds . 'data' . $ds . $slug . $ds . $kind,
				\rtrim(StateLayout::dir($app, $kind)->getRoot(), '/\\'),
				$kind
			);
		}
	}

	public function testThePathFormOfTheScaffoldingAgrees(): void
	{
		$app     = app();
		$project = $app->getProjectDir();
		$slug    = $app->getStateSlug();

		// `dirAt()` is what `oz project create` and `oz scopes add` use, before there is an app to
		// ask: it must put a scope's state exactly where a request later looks for it.
		foreach (StateLayout::kinds() as $kind) {
			self::assertSame(
				\realpath(StateLayout::dir($app, $kind)->getRoot()),
				\realpath(StateLayout::dirAt($project, $kind, $slug, true)->getRoot()),
				$kind
			);
		}
	}
}
