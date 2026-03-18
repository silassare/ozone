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
 * Reads test DB configuration from environment variables.
 *
 * Defaults to an in-memory SQLite database so the integration suite runs
 * out of the box with no external server required.
 * Override via env vars to target MySQL or PostgreSQL in CI:
 *
 *   OZ_TEST_DB_RDBMS=mysql OZ_TEST_DB_NAME=ozone_test OZ_TEST_DB_USER=root \
 *     ./vendor/bin/phpunit --testsuite Integration
 */
final class DbTestConfig
{
	public readonly string $rdbms;
	public readonly string $host;
	public readonly string $name;
	public readonly string $user;
	public readonly string $pass;

	public function __construct()
	{
		$this->rdbms = \getenv('OZ_TEST_DB_RDBMS') ?: 'sqlite';
		$this->host  = \getenv('OZ_TEST_DB_HOST') ?: ':memory:';
		$this->name  = \getenv('OZ_TEST_DB_NAME') ?: 'ozone_test';
		$this->user  = \getenv('OZ_TEST_DB_USER') ?: 'root';
		$this->pass  = \getenv('OZ_TEST_DB_PASS') ?: '';
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
		return 'pgsql' === $this->rdbms;
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
}
