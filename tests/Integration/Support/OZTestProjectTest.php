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

namespace OZONE\Tests\Integration\Support;

use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\TestCase;

/**
 * A test project reused from an earlier run runs on OZone's current dependencies, never on the ones
 * it was created with.
 *
 * @internal
 *
 * @coversNothing
 */
final class OZTestProjectTest extends TestCase
{
	private const NAME = 'harness-reuse';

	public static function tearDownAfterClass(): void
	{
		OZTestProject::create(self::NAME)->destroy();

		parent::tearDownAfterClass();
	}

	public function testAReusedProjectFollowsTheCurrentDependencySet(): void
	{
		$vendor  = OZTestProject::create(self::NAME)->getPath() . '/vendor';
		$current = \readlink($vendor);

		self::assertIsString($current);
		self::assertDirectoryExists($current);

		// As a project created before a composer update in OZone has it: another set's vendor/.
		$stale = \dirname($vendor) . '/stale-vendor';

		\mkdir($stale);
		\unlink($vendor);
		\symlink($stale, $vendor);

		OZTestProject::create(self::NAME);

		self::assertSame($current, \readlink($vendor));

		// And as a killed install leaves it: a vendor/ that is no symlink.
		\unlink($vendor);
		\mkdir($vendor . '/composer', 0o775, true);

		OZTestProject::create(self::NAME);

		self::assertSame($current, \readlink($vendor));
		self::assertFileExists($vendor . '/autoload.php');
	}
}
