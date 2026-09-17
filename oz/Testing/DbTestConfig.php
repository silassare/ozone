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

use RuntimeException;

/**
 * Holds test DB configuration for one RDBMS driver.
 *
 * SQLite needs nothing. MySQL and PostgreSQL are configured through the driver-specific env vars
 * documented in {@see allConfigured()}, which `docker/compose.yaml` exports into the PHP container:
 * a driver with no server is a broken environment, and {@see allConfigured()} refuses to provide any
 * data set rather than quietly covering SQLite alone (see {@see allowsPartialRun()}).
 */
final class DbTestConfig
{
	public readonly string $rdbms;
	public readonly string $host;
	public readonly int $port;
	public readonly string $name;
	public readonly string $user;
	public readonly string $pass;

	private function __construct(
		string $rdbms,
		string $host,
		int $port,
		string $name,
		string $user,
		string $pass,
	) {
		$this->rdbms = $rdbms;
		$this->host  = $host;
		$this->port  = $port;
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
			'OZ_DB_PORT'  => $this->port,
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
	public static function sqlite(string $host): static
	{
		return new self('sqlite', $host, 0, '', '', '');
	}

	/**
	 * Returns a MySQL config read from per-driver env vars.
	 *
	 * Returns null when OZ_TEST_MYSQL_HOST is not set.
	 */
	public static function mysql(): ?static
	{
		$envs = \getenv();

		if (empty($envs['OZ_TEST_MYSQL_HOST'])) {
			return null;
		}

		return new self(
			'mysql',
			$envs['OZ_TEST_MYSQL_HOST'],
			(int) ($envs['OZ_TEST_MYSQL_PORT'] ?? 3306),
			$envs['OZ_TEST_MYSQL_DB'] ?? 'ozone_test',
			$envs['OZ_TEST_MYSQL_USER'] ?? 'ozone_test',
			$envs['OZ_TEST_MYSQL_PASSWORD'] ?? '',
		);
	}

	/**
	 * Returns a PostgreSQL config read from per-driver env vars.
	 *
	 * Returns null when OZ_TEST_POSTGRESQL_HOST is not set.
	 */
	public static function postgresql(): ?static
	{
		$envs = \getenv();

		if (empty($envs['OZ_TEST_POSTGRESQL_HOST'])) {
			return null;
		}

		return new self(
			'postgresql',
			$envs['OZ_TEST_POSTGRESQL_HOST'],
			(int) ($envs['OZ_TEST_POSTGRESQL_PORT'] ?? 5432),
			$envs['OZ_TEST_POSTGRESQL_DB'] ?? 'ozone_test',
			$envs['OZ_TEST_POSTGRESQL_USER'] ?? 'ozone_test',
			$envs['OZ_TEST_POSTGRESQL_PASSWORD'] ?? '',
		);
	}

	/**
	 * Fails when a supported RDBMS has no server configured.
	 *
	 * Every test class reaching for a data set goes through here, so an incomplete environment stops
	 * the suite instead of silently reducing its coverage to SQLite.
	 *
	 * @throws RuntimeException
	 */
	public static function assertEveryDriverConfigured(): void
	{
		$missing = self::missingDrivers();

		if (empty($missing) || self::allowsPartialRun()) {
			return;
		}

		throw new RuntimeException(\sprintf(
			'The integration suite covers SQLite, MySQL and PostgreSQL, but no server is configured'
			. ' for %s. Run `make test-integration`: docker/compose.yaml starts them and exports their'
			. ' OZ_TEST_* variables into the PHP container. Without Docker, export them yourself (%s),'
			. ' or set OZ_TEST_ALLOW_PARTIAL=1 to run on the configured drivers only -- knowing the'
			. ' others are then not covered at all.',
			\implode(' and ', $missing),
			\implode(', ', \array_map(
				static fn (string $driver): string => 'OZ_TEST_' . \strtoupper($driver) . '_HOST',
				$missing
			))
		));
	}

	/**
	 * The server-backed drivers that are not configured.
	 *
	 * @return list<string>
	 */
	public static function missingDrivers(): array
	{
		$missing = [];

		if (null === self::mysql()) {
			$missing[] = 'mysql';
		}

		if (null === self::postgresql()) {
			$missing[] = 'postgresql';
		}

		return $missing;
	}

	/**
	 * Whether the suite may run on the configured drivers only.
	 *
	 * Set `OZ_TEST_ALLOW_PARTIAL=1` for it, knowing the others are then not covered at all.
	 *
	 * @return bool
	 */
	public static function allowsPartialRun(): bool
	{
		$value = \getenv('OZ_TEST_ALLOW_PARTIAL');

		return false !== $value && '' !== $value && '0' !== $value;
	}

	/**
	 * Returns all DB configurations that are currently available, keyed by
	 * driver name so PHPUnit @dataProvider output is readable.
	 *
	 * Every configured driver is returned; the suite refuses to start when one is missing, unless
	 * {@see self::allowsPartialRun()}.
	 *
	 * A `$tag` string is appended to the SQLite filename so test classes that
	 * run concurrently (or sequentially with stale files) do not share the same
	 * SQLite database file. Always pass a short class-specific tag such as
	 * `'migrations'` or `'dbbuild'` from every DB-related test class.
	 *
	 * Usage in a test class:
	 *
	 *   public static function provideDbConfig(): array
	 *   {
	 *       return DbTestConfig::allConfigured('my-feature');
	 *   }
	 *
	 *   @dataProvider provideDbConfig
	 *   public function testSomething(DbTestConfig $config): void { ... }
	 *
	 * @param string $tag short slug appended to the SQLite filename (e.g. 'migrations')
	 *
	 * @return array<string, array{DbTestConfig}>
	 */
	public static function allConfigured(string $tag = ''): array
	{
		self::assertEveryDriverConfigured();

		$suffix  = '' !== $tag ? "_{$tag}" : '';
		$configs = [
			'sqlite' => [self::sqlite(\sys_get_temp_dir() . '/oz_test_' . \getmypid() . $suffix . '.db')],
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
