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

use OZONE\Core\Auth\TwoFactor\TOTP;
use OZONE\Core\Testing\OZTestProject;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Signs in with two-factor authentication by an authenticator app, against a served project: the
 * challenge belongs to the session that signed in, and keeps its try limit.
 *
 * @internal
 */
#[CoversNothing]
final class TwoFactorTest extends TestCase
{
	private const string DB_FILE       = 'two_factor_test.sqlite';
	private const string USER_EMAIL    = 'jane.doe@example.com';
	private const string USER_PASSWORD = 'Jane_Pass_42';

	private static ?OZTestProject $project = null;

	private static ?Process $server = null;

	private static int $port = 0;

	private static string $host = '127.0.0.1';

	private static string $secret = '';

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$project = OZTestProject::create('two-factor', fresh: true);
		$path    = $project->getPath();

		$project->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $path . \DIRECTORY_SEPARATOR . self::DB_FILE,
		]);

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

		$project->writeFileFromStub('EnableTotp', 'enable_totp.php', ['email' => self::USER_EMAIL]);

		$enable = new Process([\PHP_BINARY, 'enable_totp.php'], $path);
		$enable->mustRun();

		self::$secret = \trim($enable->getOutput());

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

	public static function tearDownAfterClass(): void
	{
		if (self::$server?->isRunning()) {
			self::$server->stop(3);
		}

		self::$project?->destroy();

		self::$server  = null;
		self::$project = null;

		parent::tearDownAfterClass();
	}

	/**
	 * The code answered from another session is refused without spending the challenge: the session
	 * that signed in still completes it.
	 */
	public function testTheChallengeBelongsToTheSessionThatSignedIn(): void
	{
		[$session, $ref] = $this->signIn();

		// Another browser, with a session of its own, holding the reference and the right code.
		[$other] = $this->signIn();

		[$status, $body] = $this->request('POST', '/auth/' . $ref . '/authorize', [
			'code' => TOTP::compute(self::$secret),
		], $other);

		self::assertSame(401, $status, $body);
		self::assertSame('OZ_2FA_SESSION_MISMATCH', \json_decode($body, true)['msg'] ?? null, $body);

		[$status, $body] = $this->request('GET', '/auth/' . $ref . '/state');

		self::assertSame('PENDING', \json_decode($body, true)['data']['auth_state'] ?? null, $body);

		[$status, $body] = $this->request('POST', '/auth/' . $ref . '/authorize', [
			'code' => TOTP::compute(self::$secret),
		], $session);

		self::assertSame(200, $status, $body);
	}

	/**
	 * Once its tries are used up, the challenge is refused, and the right code does not grant it.
	 */
	public function testTheChallengeKeepsItsTryLimit(): void
	{
		[$session, $ref] = $this->signIn();

		for ($i = 0; $i < 5; ++$i) {
			// Not digits: six of them could be the current code.
			$this->request('POST', '/auth/' . $ref . '/authorize', ['code' => 'wrong'], $session);
		}

		[$status, $body] = $this->request('POST', '/auth/' . $ref . '/authorize', [
			'code' => TOTP::compute(self::$secret),
		], $session);

		self::assertSame(401, $status, $body);
		self::assertSame('OZ_AUTH_TOO_MUCH_ATTEMPT', \json_decode($body, true)['msg'] ?? null, $body);

		[$status, $body] = $this->request('GET', '/auth/' . $ref . '/state');

		self::assertSame('REFUSED', \json_decode($body, true)['data']['auth_state'] ?? null, $body);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Signs the user in, up to the challenge.
	 *
	 * @return array{0: array{sid: string, xsrf: string}, 1: string} the session and the reference of
	 *                                                               the challenge
	 */
	private function signIn(): array
	{
		[$status, $body, $headers] = $this->request('POST', '/login', [
			'auth_user_type'             => 'user',
			'auth_user_identifier_type'  => 'email',
			'auth_user_identifier_value' => self::USER_EMAIL,
			'auth_user_password'         => self::USER_PASSWORD,
		]);
		$data = \json_decode($body, true);

		self::assertSame(401, $status, $body);
		self::assertSame('OZ_2FA_REQUIRED', $data['msg'] ?? null, $body);

		$ref = $data['data']['auth_ref'] ?? null;

		self::assertIsString($ref, $body);

		return [self::session($headers), $ref];
	}

	/**
	 * Sends a request to the project's server.
	 *
	 * @param array<string, string>                 $fields
	 * @param null|array{sid: string, xsrf: string} $session the session to send the request in, with
	 *                                                       its CSRF token, as a browser client does
	 *
	 * @return array{0: int, 1: string, 2: list<string>}
	 */
	private function request(string $method, string $path, array $fields = [], ?array $session = null): array
	{
		$url = 'http://' . self::$host . ':' . self::$port . $path;

		$opts = [
			'method'          => $method,
			'timeout'         => 10,
			'ignore_errors'   => true,
			'follow_location' => false,
			'header'          => "Accept: application/json\r\n",
		];

		if (null !== $session) {
			$opts['header'] .= 'Cookie: OZONE_SID=' . $session['sid'] . "\r\n"
				. 'X-XSRF-TOKEN: ' . $session['xsrf'] . "\r\n";
		}

		if ([] !== $fields) {
			$opts['content'] = \http_build_query($fields);
			$opts['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
		}

		$ctx  = \stream_context_create(['http' => $opts]);
		$body = @\file_get_contents($url, false, $ctx);

		if (false === $body) {
			return [0, '', []];
		}

		$status = 0;

		if (
			!empty((\http_get_last_response_headers() ?? [])[0])
			&& \preg_match('/HTTP\/\d+(?:\.\d+)? (\d+)/', (\http_get_last_response_headers() ?? [])[0], $m)
		) {
			$status = (int) $m[1];
		}

		return [$status, $body, \http_get_last_response_headers() ?? []];
	}

	/**
	 * The session a response opened, and its CSRF token.
	 *
	 * @param list<string> $headers
	 *
	 * @return array{sid: string, xsrf: string}
	 */
	private static function session(array $headers): array
	{
		$cookies = self::cookies($headers);

		self::assertNotEmpty($cookies['OZONE_SID'] ?? null, \implode("\n", $headers));
		self::assertNotEmpty($cookies['XSRF-TOKEN'] ?? null, \implode("\n", $headers));

		return ['sid' => $cookies['OZONE_SID'], 'xsrf' => $cookies['XSRF-TOKEN']];
	}

	/**
	 * The cookies a response sets, by name.
	 *
	 * @param list<string> $headers
	 *
	 * @return array<string, string>
	 */
	private static function cookies(array $headers): array
	{
		$cookies = [];

		foreach ($headers as $header) {
			if (\preg_match('~^Set-Cookie:\s*([^=;]+)=([^;]*)~i', $header, $m)) {
				$cookies[\urldecode($m[1])] = \urldecode($m[2]);
			}
		}

		return $cookies;
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
