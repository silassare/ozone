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

namespace OZONE\Tests\Testing;

use OZONE\Core\Testing\OZTestProject;
use OZONE\Core\Testing\Sandbox;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

/**
 * Class OZTestProjectLockTest.
 *
 * How a test project pins the running repository's dependencies: released and branch packages to what
 * the lock records, path-repository packages (local sibling checkouts) to their directory.
 *
 * @internal
 *
 * @covers \OZONE\Core\Testing\OZTestProject
 */
final class OZTestProjectLockTest extends TestCase
{
	private string $dir;

	protected function setUp(): void
	{
		parent::setUp();

		$this->dir = \sys_get_temp_dir() . '/oz_lock_' . \bin2hex(\random_bytes(4));

		\mkdir($this->dir . '/root', 0o775, true);
		\mkdir($this->dir . '/sibling', 0o775, true);
	}

	protected function tearDown(): void
	{
		Sandbox::remove($this->dir);

		parent::tearDown();
	}

	public function testPinsReleasesBranchesAndPathPackages(): void
	{
		$this->writeLock([
			['name' => 'vendor/released', 'version' => 'v1.2.3', 'dist' => ['type' => 'zip', 'reference' => 'abc']],
			['name' => 'vendor/branch', 'version' => 'dev-main', 'source' => ['type' => 'git', 'reference' => 'f00']],
			['name' => 'vendor/sibling', 'version' => 'dev-main', 'dist' => ['type' => 'path', 'url' => '../sibling']],
		]);

		self::assertSame([
			'vendor/released' => ['v1.2.3', null],
			'vendor/branch'   => ['dev-main#f00', null],
			'vendor/sibling'  => ['dev-main', \realpath($this->dir . '/sibling')],
		], self::lockedPackages($this->dir . '/root'));
	}

	public function testAbsolutePathPackage(): void
	{
		$this->writeLock([
			[
				'name'    => 'vendor/sibling',
				'version' => 'dev-main',
				'dist'    => ['type' => 'path', 'url' => $this->dir . '/sibling'],
			],
		]);

		self::assertSame(
			['vendor/sibling' => ['dev-main', \realpath($this->dir . '/sibling')]],
			self::lockedPackages($this->dir . '/root')
		);
	}

	public function testMissingPathPackageDirectoryThrows(): void
	{
		$this->writeLock([
			['name' => 'vendor/gone', 'version' => 'dev-main', 'dist' => ['type' => 'path', 'url' => '../gone']],
		]);

		$this->expectException(RuntimeException::class);

		self::lockedPackages($this->dir . '/root');
	}

	public function testNoLockPinsNothing(): void
	{
		self::assertSame([], self::lockedPackages($this->dir . '/root'));
	}

	public function testWriteFileFromStubNeedsAStubsDirectory(): void
	{
		$class = new ReflectionClass(OZTestProject::class);
		$prop  = $class->getProperty('stubs_dir');

		$prop->setAccessible(true);
		$previous = $prop->getValue();

		try {
			$prop->setValue(null, null);

			$this->expectException(RuntimeException::class);

			$class->newInstanceWithoutConstructor()->writeFileFromStub('Anything', 'app/Anything.php', []);
		} finally {
			$prop->setValue(null, $previous);
		}
	}

	/**
	 * @param list<array<string, mixed>> $packages
	 */
	private function writeLock(array $packages): void
	{
		\file_put_contents(
			$this->dir . '/root/composer.lock',
			\json_encode(['packages' => $packages], \JSON_THROW_ON_ERROR)
		);
	}

	/**
	 * @return array<string, array{0: string, 1: null|string}>
	 */
	private static function lockedPackages(string $root_dir): array
	{
		$method = new ReflectionMethod(OZTestProject::class, 'lockedPackages');

		$method->setAccessible(true);

		/** @var array<string, array{0: string, 1: null|string}> */
		return $method->invoke(null, $root_dir);
	}
}
