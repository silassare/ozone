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

use OZONE\Core\App\Db;
use OZONE\Core\Db\Base\OZCountriesQueryBase;
use OZONE\Core\Db\OZCountry;
use OZONE\Core\Loader\ClassLoader;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OZONE\Core\App\Db
 *
 * @internal
 */
final class DbTest extends TestCase
{
	public function testOrmClassesLoadFromWhereGoblGeneratedThem(): void
	{
		$entity = ClassLoader::findFile(OZCountry::class);
		$base   = ClassLoader::findFile(OZCountriesQueryBase::class);
		$dir    = \DIRECTORY_SEPARATOR . 'Db' . \DIRECTORY_SEPARATOR;

		self::assertIsString($entity);
		self::assertIsString($base);
		self::assertFileExists($entity);
		self::assertFileExists($base);
		self::assertStringEndsWith($dir . 'OZCountry.php', \str_replace('/', \DIRECTORY_SEPARATOR, $entity));
		self::assertStringEndsWith(
			$dir . 'Base' . \DIRECTORY_SEPARATOR . 'OZCountriesQueryBase.php',
			\str_replace('/', \DIRECTORY_SEPARATOR, $base)
		);

		// A lazy namespace resolved by Db::initOnFirstUse(), not a directory registered up front.
		self::assertNotContains(Db::getOZoneDbNamespace() . '\\', ClassLoader::report()['lazy_namespaces']);
		self::assertArrayHasKey(Db::getOZoneDbNamespace() . '\\', ClassLoader::report()['namespaces']);
	}

	public function testOtherClassesAreLeftToTheOtherAutoloaders(): void
	{
		self::assertNull(ClassLoader::findFile('OZONE\Core\Db\NotGenerated'));
	}
}
