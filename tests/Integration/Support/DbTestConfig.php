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

/**
 * Holds test DB configuration for one RDBMS driver.
 *
 * Out of the box (no env vars), only SQLite is available.
 * To enable MySQL or PostgreSQL, set the driver-specific env vars
 * documented in {@see allConfigured()}.
 */
final class DbTestConfig
{
	public readonly string $rdbms;
	public readonly string $host;
	public readonly string $name;
	public readonly string $user;
	public readonly string $pass;

	private function __construct(
		string $rdbms,
		string $host,
		string $name,
		string $user,
		string $pass,
	) {
		$this->rdbms = $rdbms;
		$this->host  = $host;
		$this->name  = $name;
		$this->user  = $user;
		$this->pass  = $pass;
	}

	public function isSQLite(): bool
	{
		return 'sqlite' === $this->rdbms;
	}

	public function isMySQL(): bool
	{
		return 'mysql' === $this->rdbms;
	}

	public function isPostgreSQL(): bool
	{
		return 'postgresql' === $this->rdbms;
	}

	/**
	 * Returns the DB config as .env key-value pairs suitable for
	 * {@see OZTestProject::writeEnv()}.
	 *
	 * @return array<string, string>
	 */
	public function toEnvArray(): array
	{
		return [
			'OZ_DB_RDBMS' => $this->rdbms,
			'OZ_DB_HOST'  => $this->host,
			'OZ_DB_NAME'  => $this->name,
			'OZ_DB_USER'  => $this->user,
			'OZ_DB_PASS'  => $this->pass,
		];
	}

	/**
	 * Returns a SQLite config backed by a real file (not :memory:) so the DB
	 * persists across separate process invocations (e.g. migrations run + backup).
	 *
	 * @param string $host absolute path to the SQLite database file
	 */
	public static function sqlite(string $host): self
	{
		return new self('sqlite', $host, '', '', '');
	}

	/**
	 * Returns a MySQL config read from per-driver env vars:
	 *
	 *   OZ_TEST_MYSQL_HOST  (default: 127.0.0.1)
	 *   OZ_TEST_MYSQL_NAME  (default: ozone_test)
	 *   OZ_TEST_MYSQL_USER  (default: root)
	 *   OZ_TEST_MYSQL_PASS  (default: '')
	 *
	 * Returns null when OZ_TEST_MYSQL_HOST is not set.
	 */
	public static function mysql(): ?self
	{
		$envs = \getenv();

		if (empty($envs['OZ_TEST_MYSQL_HOST'])) {
			return null;
		}

		return new self(
			'mysql',
			$envs['OZ_TEST_MYSQL_HOST'],
			$envs['OZ_TEST_MYSQL_NAME'] ?? 'ozone_test',
			$envs['OZ_TEST_MYSQL_USER'] ?? 'root',
			$envs['OZ_TEST_MYSQL_PASS'] ?? '',
		);
	}

	/**
	 * Returns a PostgreSQL config read from per-driver env vars:
	 *
	 *   OZ_TEST_PGSQL_HOST  (default: 127.0.0.1)
	 *   OZ_TEST_PGSQL_NAME  (default: ozone_test)
	 *   OZ_TEST_PGSQL_USER  (default: postgres)
	 *   OZ_TEST_PGSQL_PASS  (default: '')
	 *
	 * Returns null when OZ_TEST_PGSQL_HOST is not set.
	 */
	public static function postgresql(): ?self
	{
		$envs = \getenv();

		if (empty($envs['OZ_TEST_PGSQL_HOST'])) {
			return null;
		}

		return new self(
			'postgresql',
			$envs['OZ_TEST_PGSQL_HOST'],
			$envs['OZ_TEST_PGSQL_NAME'] ?? 'ozone_test',
			$envs['OZ_TEST_PGSQL_USER'] ?? 'postgres',
			$envs['OZ_TEST_PGSQL_PASS'] ?? '',
		);
	}

	/**
	 * Returns all DB configurations that are currently available, keyed by
	 * driver name so PHPUnit @dataProvider output is readable.
	 *
	 * Usage in a test class:
	 *
	 *   public static function provideDbConfig(): array
	 *   {
	 *       return DbTestConfig::allConfigured();
	 *   }
	 *
	 *   @dataProvider provideDbConfig
	 *   public function testSomething(DbTestConfig $config): void { ... }
	 *
	 * @return array<string, array{DbTestConfig}>
	 */
	public static function allConfigured(): array
	{
		$configs = [
			'sqlite' => [self::sqlite(\sys_get_temp_dir() . '/oz_test_' . \getmypid() . '.db')],
		];

		$mysql = self::mysql();
		if (null !== $mysql) {
			$configs['mysql'] = [$mysql];
		}

		$postgresql = self::postgresql();
		if (null !== $postgresql) {
			$configs['postgresql'] = [$postgresql];
		}

		return $configs;
	}
}
