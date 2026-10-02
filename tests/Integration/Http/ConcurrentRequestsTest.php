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

namespace OZONE\Tests\Integration\Http;

use OZONE\Core\Testing\DbTestConfig;
use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\TestCase;

/**
 * Class ConcurrentRequestsTest.
 *
 * Requests a server answers at the same time, as a burst from one browser or from four at once:
 * what they write together must not collide.
 *
 * @internal
 *
 * @coversNothing
 */
final class ConcurrentRequestsTest extends TestCase
{
	/** Requests sent at once, the server's workers answering them, and the route's limit. */
	private const BURST   = 12;
	private const WORKERS = 8;
	private const LIMIT   = 5;

	/**
	 * The first hits of a rate limit arrive together: each writes the limit's new key. Writing it by
	 * reading first, then inserting, made all but one fail on its unique key (500). And they are
	 * counted together: counting by reading then writing let more of them pass than the limit.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testABurstOfFirstHitsIsAnsweredAndCountedWhole(DbTestConfig $config): void
	{
		$proj = OZTestProject::create('concurrent-' . $config->rdbms, fresh: true);

		$proj->writeEnv($config->toEnvArray());
		$proj->setSetting('oz.files', 'OZ_UPLOAD_ANONYMOUS_RATE_LIMIT', self::LIMIT);
		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		[$server, $host, $port] = $proj->startServer('api', '127.0.0.1', self::WORKERS);

		try {
			$statuses = self::burst("http://{$host}:{$port}/upload/chunk/start", self::BURST);
		} finally {
			$server->stop(3);
		}

		$seen = \implode(', ', $statuses);

		self::assertCount(self::BURST, $statuses);
		self::assertNotContains(500, $statuses, 'Some requests failed: ' . $seen);
		// As many pass as the limit allows, no more: the others are refused (429).
		self::assertCount(self::BURST - self::LIMIT, \array_keys($statuses, 429, true), $seen);
	}

	/**
	 * Sign-ups with one email at once: each passes the check that the email is free, then inserts.
	 * One user is created; the others get the email field's error, never a 500.
	 */
	public function testSignUpsWithOneEmailAtOnceMakeOneUser(): void
	{
		$proj = OZTestProject::create('concurrent-signup', fresh: true);
		$db   = $proj->getPath() . '/signup.sqlite';

		$proj->writeEnv(['OZ_DB_RDBMS' => 'sqlite', 'OZ_DB_HOST' => $db]);
		// No email to prove here: the test is about what reaches the database.
		$proj->setSetting('oz.auth.verification', 'OZ_SIGNUP_VERIFICATION', []);
		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		self::seedCountry($db, 'BJ');

		[$server, $host, $port] = $proj->startServer('api', '127.0.0.1', self::WORKERS);

		try {
			$answers = self::burstAnswers("http://{$host}:{$port}/signup", 8, [
				'civility'     => 'Mr',
				'display_name' => 'Twin',
				'first_name'   => 'Twin',
				'last_name'    => 'Signup',
				'email'        => 'twin@example.com',
				'gender'       => 'Male',
				'birth_date'   => '1990-05-17',
				'pass'         => 'Twin_Pass_42',
				'cc2'               => 'BJ',
			]);
		} finally {
			$server->stop(3);
		}

		$seen = \implode(', ', \array_map(static fn (array $a): string => $a[0] . ' ' . $a[1], $answers));

		self::assertCount(1, \array_filter($answers, static fn (array $a): bool => 200 === $a[0]), $seen);
		self::assertCount(
			7,
			\array_filter($answers, static fn (array $a): bool => 'OZ_FIELD_EMAIL_ALREADY_REGISTERED email' === $a[1]),
			$seen
		);
	}

	/**
	 * @return iterable<string, array{DbTestConfig}>
	 */
	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('concurrent');
	}

	/**
	 * Sends the same POST `$count` times at once, and answers each status.
	 *
	 * @return list<int>
	 */
	private static function burst(string $url, int $count): array
	{
		$multi   = \curl_multi_init();
		$handles = [];

		for ($i = 0; $i < $count; ++$i) {
			$handle = \curl_init($url);

			\curl_setopt_array($handle, [
				\CURLOPT_POST           => true,
				\CURLOPT_POSTFIELDS     => \http_build_query(['size' => '16']),
				\CURLOPT_RETURNTRANSFER => true,
				\CURLOPT_HTTPHEADER     => ['Accept: application/json'],
				\CURLOPT_TIMEOUT        => 30,
			]);
			\curl_multi_add_handle($multi, $handle);
			$handles[] = $handle;
		}

		do {
			$status = \curl_multi_exec($multi, $running);

			if ($running) {
				\curl_multi_select($multi);
			}
		} while ($running && \CURLM_OK === $status);

		$statuses = [];

		foreach ($handles as $handle) {
			$statuses[] = (int) \curl_getinfo($handle, \CURLINFO_RESPONSE_CODE);
			\curl_multi_remove_handle($multi, $handle);
		}

		\curl_multi_close($multi);

		return $statuses;
	}

	/**
	 * Sends the same POST `$count` times at once: each answer's status and message.
	 *
	 * @param array<string, string> $fields
	 *
	 * @return list<array{int, string}>
	 */
	private static function burstAnswers(string $url, int $count, array $fields): array
	{
		$multi   = \curl_multi_init();
		$handles = [];

		for ($i = 0; $i < $count; ++$i) {
			$handle = \curl_init($url);

			\curl_setopt_array($handle, [
				\CURLOPT_POST           => true,
				\CURLOPT_POSTFIELDS     => \http_build_query($fields),
				\CURLOPT_RETURNTRANSFER => true,
				\CURLOPT_HTTPHEADER     => ['Accept: application/json'],
				\CURLOPT_TIMEOUT        => 30,
			]);
			\curl_multi_add_handle($multi, $handle);
			$handles[] = $handle;
		}

		do {
			$status = \curl_multi_exec($multi, $running);

			if ($running) {
				\curl_multi_select($multi);
			}
		} while ($running && \CURLM_OK === $status);

		$answers = [];

		foreach ($handles as $handle) {
			$body      = \json_decode((string) \curl_multi_getcontent($handle), true);
			$answers[] = [
				(int) \curl_getinfo($handle, \CURLINFO_RESPONSE_CODE),
				\is_array($body)
					? \trim(($body['msg'] ?? '') . ' ' . ($body['data']['field'] ?? \json_encode($body['data'] ?? null)))
					: '',
			];
			\curl_multi_remove_handle($multi, $handle);
		}

		\curl_multi_close($multi);

		return $answers;
	}

	/**
	 * Adds an allowed country: a user needs one (`user_cc2`) and no command creates countries.
	 */
	private static function seedCountry(string $db, string $cc2): void
	{
		$pdo   = new \PDO('sqlite:' . $db, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
		$table = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE '%oz_countries'")
			->fetchColumn();

		self::assertIsString($table);

		$now = (string) \time();

		$pdo->prepare(\sprintf(
			'INSERT INTO "%s" (country_cc2, country_calling_code, country_name, country_name_real, country_data,'
				. ' country_is_valid, country_created_at, country_updated_at, country_deleted, country_deleted_at)'
				. ' VALUES (?, ?, ?, ?, ?, 1, ?, ?, 0, NULL)',
			$table
		))->execute([$cc2, '+229', 'Benin', 'Benin', '{}', $now, $now]);
	}
}
