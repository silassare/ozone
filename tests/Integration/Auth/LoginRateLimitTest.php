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

namespace OZONE\Tests\Integration\Auth;

use Override;
use OZONE\Core\Testing\OZTestProject;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Tests the per-IP rate limit of the login route against a real served project: asking the route
 * for its form (a form discovery) spends none of the attempts, a submission spends one.
 *
 * Every request comes from 127.0.0.1, so they all draw on one quota.
 *
 * @internal
 */
#[CoversNothing]
final class LoginRateLimitTest extends TestCase
{
	private const string DB_FILE          = 'login_rate_limit_test.sqlite';
	private const string USER_EMAIL       = 'jane.doe@example.com';
	private const string USER_PASSWORD    = 'Jane_Pass_42';
	private const string DISCOVERY_HEADER = 'X-OZONE-Form-Discovery: ?1';

	/**
	 * Login submissions allowed per IP in the window: low, so a test reaches it quickly, and below
	 * `OZ_AUTH_LOGIN_MAX_FAILURES` (5), so the per-account lock does not answer first.
	 */
	private const int RATE = 3;

	private static ?OZTestProject $project = null;

	private static ?Process $server = null;

	private static int $port = 0;

	private static string $host = '127.0.0.1';

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$project = OZTestProject::create('login-rate-limit', fresh: true);

		// File-based SQLite so the schema persists across separate PHP CLI processes.
		$project->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $project->getPath() . \DIRECTORY_SEPARATOR . self::DB_FILE,
		]);

		$project->setSetting('oz.auth', 'OZ_AUTH_LOGIN_IP_RATE', self::RATE);
		// Long enough that the window cannot end in the middle of the test.
		$project->setSetting('oz.auth', 'OZ_AUTH_LOGIN_IP_INTERVAL', 3600);

		$project->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$project->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$project->oz('migrations', 'run', '--skip-backup')->mustRun();

		self::seedCountry($project, 'BJ');

		$project->oz(
			'users',
			'add',
			'--user_civility=Mrs',
			'--user_display_name=Jane Doe',
			'--user_first_name=Jane',
			'--user_last_name=Doe',
			'--user_email=' . self::USER_EMAIL,
			'--user_gender=Female',
			'--user_birth_date=1990-05-17',
			'--user_pass=' . self::USER_PASSWORD,
			'--user_cc2=BJ',
		)->mustRun();

		try {
			[$server, , $port] = $project->startServer('api', self::$host);
		} catch (RuntimeException $e) {
			$project->destroy();
			self::fail($e->getMessage());
		}

		self::$project = $project;
		self::$server  = $server;
		self::$port    = $port;
	}

	#[Override]
	public static function tearDownAfterClass(): void
	{
		if (self::$server?->isRunning()) {
			self::$server->stop(3);
		}

		self::$project?->destroy();

		self::$server  = null;
		self::$project = null;
		self::$port    = 0;

		parent::tearDownAfterClass();
	}

	/**
	 * Discovering the login form many times past the limit spends none of its attempts: the sign-in
	 * that follows is allowed with the whole quota left, and the submissions past the limit are
	 * still refused.
	 */
	public function testAFormDiscoveryDoesNotSpendALoginAttempt(): void
	{
		for ($i = 0; $i < 3 * self::RATE; ++$i) {
			$this->assertDiscovered();
		}

		// The first submission of the window: the quota is whole.
		[$status, $body, $headers] = $this->login(self::USER_PASSWORD);

		self::assertSame(200, $status, $body);
		self::assertSame('OZ_USER_SIGN_IN_DONE', \json_decode($body, true)['msg'] ?? null, $body);
		self::assertSame((string) (self::RATE - 1), self::header($headers, 'X-RateLimit-Remaining'), $body);

		// The rest of the quota is spent by submissions, which are counted.
		for ($i = 1; $i < self::RATE; ++$i) {
			[, $body] = $this->login('wrong-password');

			self::assertSame('OZ_AUTH_INVALID_CREDENTIALS', \json_decode($body, true)['msg'] ?? null, $body);
		}

		[$status, $body] = $this->login(self::USER_PASSWORD);

		self::assertSame(429, $status, $body);
		self::assertSame('OZ_ERROR_RATE_LIMIT_REACHED', \json_decode($body, true)['msg'] ?? null, $body);

		// The form is still described while the submissions are refused.
		$this->assertDiscovered();
	}

	/**
	 * Asks the login route for its form, and asserts it was answered.
	 */
	private function assertDiscovered(): void
	{
		[$status, $body, $headers] = $this->request('POST', '/login', [self::DISCOVERY_HEADER]);
		$data                      = \json_decode($body, true);

		self::assertSame(200, $status, $body);
		self::assertSame(0, $data['error'] ?? null, $body);
		self::assertIsArray($data['form'] ?? null, $body);
		self::assertStringContainsString('auth_user_password', $body);
		// Not counted, so not reported either.
		self::assertNull(self::header($headers, 'X-RateLimit-Remaining'), $body);
	}

	/**
	 * Submits the login form for the test user.
	 *
	 * @return array{0: int, 1: string, 2: list<string>}
	 */
	private function login(string $password): array
	{
		return $this->request('POST', '/login', [], [
			'auth_user_type'             => 'user',
			'auth_user_identifier_type'  => 'email',
			'auth_user_identifier_value' => self::USER_EMAIL,
			'auth_user_password'         => $password,
		]);
	}

	/**
	 * Sends a request to the project's server.
	 *
	 * @param list<string>          $headers
	 * @param array<string, string> $fields
	 *
	 * @return array{0: int, 1: string, 2: list<string>}
	 */
	private function request(string $method, string $path, array $headers = [], array $fields = []): array
	{
		$url = 'http://' . self::$host . ':' . self::$port . $path;

		$opts = [
			'method'          => $method,
			'timeout'         => 10,
			'ignore_errors'   => true,
			'follow_location' => false,
			'header'          => "Accept: application/json\r\n"
				. \implode('', \array_map(static fn (string $h): string => $h . "\r\n", $headers)),
		];

		if ([] !== $fields) {
			$opts['content'] = \http_build_query($fields);
			$opts['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
		}

		$ctx  = \stream_context_create(['http' => $opts]);
		$body = @\file_get_contents($url, false, $ctx);

		if (false === $body) {
			return [0, '', []];
		}

		$headers_received = \http_get_last_response_headers() ?? [];
		$status           = 0;

		if (!empty($headers_received[0]) && \preg_match('/HTTP\/\d+(?:\.\d+)? (\d+)/', $headers_received[0], $m)) {
			$status = (int) $m[1];
		}

		return [$status, $body, $headers_received];
	}

	/**
	 * The value of a response header, or null when it was not sent.
	 *
	 * @param list<string> $headers
	 */
	private static function header(array $headers, string $name): ?string
	{
		foreach ($headers as $header) {
			if (\preg_match('~^' . \preg_quote($name, '~') . ':\s*(.*)$~i', $header, $m)) {
				return \trim($m[1]);
			}
		}

		return null;
	}

	/**
	 * Seeds a country, which `oz users add` requires.
	 */
	private static function seedCountry(OZTestProject $project, string $cc2): void
	{
		$pdo = new PDO(
			'sqlite:' . $project->getPath() . \DIRECTORY_SEPARATOR . self::DB_FILE,
			null,
			null,
			[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
		);

		// Generated projects use a random table prefix.
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
