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

namespace OZONE\Tests\Collections;

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Override;
use OZONE\Core\Collections\EntityCollections;
use OZONE\Core\Collections\Interfaces\EntityCollectionsProviderInterface;
use OZONE\Core\Exceptions\RuntimeException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use stdClass;

/**
 * Class EntityCollectionsTest.
 *
 * Providers are only registered on an installed project (with a super admin), which the unit
 * suite is not: the tests call the provider loop directly.
 *
 * @internal
 *
 * @covers \OZONE\Core\Collections\EntityCollections
 */
final class EntityCollectionsTest extends TestCase
{
	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		StubCollectionsProvider::$registered_on      = [];
		OtherStubCollectionsProvider::$registered_on = [];
	}

	public function testEnabledProvidersRegisterOnTheDatabase(): void
	{
		self::registerProviders([
			StubCollectionsProvider::class      => true,
			OtherStubCollectionsProvider::class => false,
		]);

		self::assertSame([db()], StubCollectionsProvider::$registered_on);
		self::assertSame([], OtherStubCollectionsProvider::$registered_on);
	}

	public function testProvidersMustImplementTheInterface(): void
	{
		$this->expectException(RuntimeException::class);

		self::registerProviders([stdClass::class => true]);
	}

	/**
	 * @param array<string, bool> $providers
	 */
	private static function registerProviders(array $providers): void
	{
		(new ReflectionMethod(EntityCollections::class, 'registerProviders'))->invoke(null, db(), $providers);
	}
}

/**
 * @internal
 */
final class StubCollectionsProvider implements EntityCollectionsProviderInterface
{
	/**
	 * @var list<RDBMSInterface>
	 */
	public static array $registered_on = [];

	#[Override]
	public static function register(RDBMSInterface $db): void
	{
		self::$registered_on[] = $db;
	}
}

/**
 * @internal
 */
final class OtherStubCollectionsProvider implements EntityCollectionsProviderInterface
{
	/**
	 * @var list<RDBMSInterface>
	 */
	public static array $registered_on = [];

	#[Override]
	public static function register(RDBMSInterface $db): void
	{
		self::$registered_on[] = $db;
	}
}
