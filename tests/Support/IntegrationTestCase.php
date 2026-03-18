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

namespace OZONE\Tests\Support;

use OZONE\Core\App\Db;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Base class for integration tests that require a real database.
 *
 * Schema lifecycle:
 *  - The OZone dev schema is generated (DROP + CREATE) exactly once per
 *    PHPUnit process, on the first test class that extends this base.
 *  - Individual test classes are responsible for their own data setup and
 *    teardown (insert rows in setUp(), delete or truncate in tearDown()/
 *    tearDownAfterClass()).
 *
 * Database connection:
 *  - Configured via environment variables read by noop/settings/oz.db.php.
 *  - Defaults to MySQL on 127.0.0.1, database "ozone_test", user "root".
 *  - Set OZ_TEST_DB_RDBMS=sqlite and OZ_TEST_DB_HOST=:memory: to use
 *    an in-memory SQLite database instead (fast, no external server required).
 *
 * @see run_test_integration — the companion script that exports env vars and
 *      creates the MySQL test database before running PHPUnit.
 */
abstract class IntegrationTestCase extends TestCase
{
	/**
	 * Tracks whether the schema DDL has been executed in this process.
	 *
	 * Declared private so subclasses share the single flag on this class.
	 */
	private static bool $schemaReady = false;

	/**
	 * {@inheritDoc}
	 */
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		// Ensure the test DB schema is created exactly once per process.
		if (!self::$schemaReady) {
			self::applySchema();
			self::$schemaReady = true;
		}
	}

	/**
	 * Generates and executes the full CREATE TABLE DDL from the OZone dev
	 * schema.
	 *
	 * The generated SQL includes DROP TABLE IF EXISTS (with FK checks
	 * disabled for MySQL), so this is safe to call on both fresh databases
	 * and databases that already contain tables from a prior run.
	 */
	protected static function applySchema(): void
	{
		$db  = Db::get();
		$sql = \trim($db->getGenerator()->buildDatabase());

		if (!empty($sql)) {
			$db->executeMulti($sql);
		}
	}

	/**
	 * Truncates the given tables, removing all rows and resetting
	 * auto-increment counters.
	 *
	 * Table names must be the bare names without any DB prefix — the ORM
	 * entity constants (e.g. OZJob::TABLE_NAME) give the correct value.
	 *
	 * @param string ...$table_names bare table names, e.g. 'oz_jobs'
	 */
	protected static function truncateTables(string ...$table_names): void
	{
		$db     = Db::get();
		$prefix = $db->getConfig()->getDbTablePrefix();

		foreach ($table_names as $name) {
			$full = $prefix . $name;

			try {
				$db->executeMulti(
					'SET FOREIGN_KEY_CHECKS=0; TRUNCATE TABLE `' . $full . '`; SET FOREIGN_KEY_CHECKS=1;'
				);
			} catch (Throwable) {
				// Table may not exist in some driver configurations (e.g. SQLite
				// where TRUNCATE is unsupported). Ignore silently.
			}
		}
	}

	/**
	 * Returns whether the underlying RDBMS is SQLite.
	 *
	 * Useful to skip assertions that rely on MySQL-specific behaviour.
	 */
	protected static function isSQLite(): bool
	{
		return 'sqlite' === Db::get()->getType();
	}
}
