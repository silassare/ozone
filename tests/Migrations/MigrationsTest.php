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

namespace OZONE\Tests\Migrations;

use Gobl\DBAL\Interfaces\MigrationInterface;
use Gobl\DBAL\MigrationMode;
use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Migrations\Enums\MigrationsState;
use OZONE\Core\Migrations\Migrations;
use OZONE\Core\Stores\CacheRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Class MigrationsTest.
 *
 * The migrations list and the database version are fed through the runtime cache
 * {@see Migrations} reads them from, so no migration file nor database is needed.
 *
 * @internal
 *
 * @covers \OZONE\Core\Migrations\Migrations
 */
final class MigrationsTest extends TestCase
{
	#[Override]
	protected function tearDown(): void
	{
		Migrations::clearCache();

		parent::tearDown();
	}

	public function testLookupsByVersion(): void
	{
		[$v1, $v2, $v3] = self::seed([1, 2, 3], 1);
		$migrations     = new Migrations();

		self::assertSame($v3, $migrations->getLatestMigration());
		self::assertSame($v2, $migrations->getMigration(2));
		self::assertNull($migrations->getMigration(9));
		self::assertSame($v1, $migrations->getPreviousMigration(2));
		self::assertNull($migrations->getPreviousMigration(1));
		self::assertSame($v3, $migrations->getNextMigration(2));
		self::assertNull($migrations->getNextMigration(3));
		self::assertSame([$v2, $v3], $migrations->getMigrationBetween(2, 3));
	}

	public function testPendingMigrationsAreTheOnesAboveTheDbVersion(): void
	{
		[, $v2, $v3] = self::seed([1, 2, 3], 1);
		$migrations  = new Migrations();

		self::assertTrue($migrations->hasPendingMigrations());
		self::assertSame([$v2, $v3], $migrations->getPendingMigrations());

		self::seed([1, 2, 3], 3);

		self::assertFalse($migrations->hasPendingMigrations());
		self::assertSame([], $migrations->getPendingMigrations());
	}

	public function testNoMigrationYet(): void
	{
		self::seed([], 0);
		$migrations = new Migrations();

		self::assertNull($migrations->getLatestMigration());
		self::assertFalse($migrations->hasPendingMigrations());
	}

	public function testStateComparesTheDbVersionToTheLatestMigrationFile(): void
	{
		$cases = [
			0 => MigrationsState::NOT_INSTALLED,
			1 => MigrationsState::PENDING,
			2 => MigrationsState::INSTALLED,
			3 => MigrationsState::ROLLBACK,
		];

		foreach ($cases as $db_version => $state) {
			self::seed([1, 2], $db_version);

			self::assertSame($state, Migrations::getState(), "db version {$db_version}");
		}
	}

	public function testSourceCodeVersionIsTheLatestMigrationFileNotTheSetting(): void
	{
		// The setting records what the database is at; it is only written once a migration runs, so
		// a created but unrun migration is invisible in it. The files are the source of truth.
		Settings::set('oz.db.migrations', 'OZ_MIGRATION_VERSION', 1);

		try {
			self::seed([1, 2], 1);

			self::assertSame(2, Migrations::getSourceCodeDbVersion());
			self::assertSame(1, Migrations::getInstalledDbVersion());
			self::assertSame(MigrationsState::PENDING, Migrations::getState());
			self::assertTrue((new Migrations())->hasPendingMigrations());
		} finally {
			Settings::unset('oz.db.migrations', 'OZ_MIGRATION_VERSION');
		}
	}

	public function testSourceCodeVersionIsZeroWithoutAnyMigrationFile(): void
	{
		Settings::set('oz.db.migrations', 'OZ_MIGRATION_VERSION', 7);

		try {
			self::seed([], 0);

			self::assertSame(Migrations::DB_NOT_INSTALLED_VERSION, Migrations::getSourceCodeDbVersion());
			self::assertSame(7, Migrations::getInstalledDbVersion());
		} finally {
			Settings::unset('oz.db.migrations', 'OZ_MIGRATION_VERSION');
		}
	}

	public function testNotInstalledDatabaseHasPendingMigrations(): void
	{
		// getState() calls it NOT_INSTALLED, but the migrations have still never run: `oz migrations
		// check` has to list them.
		self::seed([1, 2], 0);

		self::assertSame(MigrationsState::NOT_INSTALLED, Migrations::getState());
		self::assertTrue((new Migrations())->hasPendingMigrations());
	}

	/**
	 * Primes the migrations list (in version order) and the database version.
	 *
	 * @param list<int> $versions
	 *
	 * @return list<StubMigration>
	 */
	private static function seed(array $versions, int $db_version): array
	{
		$migrations = \array_map(static fn (int $version) => new StubMigration($version), $versions);
		$cache      = CacheRegistry::runtime(Migrations::class);

		$cache->set('migrations', $migrations);
		$cache->set('db_version', $db_version);

		return $migrations;
	}
}

/**
 * A migration that only has a version.
 *
 * @internal
 */
final class StubMigration implements MigrationInterface
{
	public function __construct(private readonly int $version) {}

	#[Override]
	public function getLabel(): string
	{
		return 'v' . $this->version;
	}

	#[Override]
	public function getVersion(): int
	{
		return $this->version;
	}

	#[Override]
	public function getTimestamp(): int
	{
		return 0;
	}

	#[Override]
	public function getSchema(): array
	{
		return [];
	}

	#[Override]
	public function getConfigs(): array
	{
		return [];
	}

	#[Override]
	public function up(): string
	{
		return '';
	}

	#[Override]
	public function down(): string
	{
		return '';
	}

	#[Override]
	public function beforeRun(MigrationMode $mode, string $query): bool|string
	{
		return true;
	}

	#[Override]
	public function afterRun(MigrationMode $mode): void {}
}
