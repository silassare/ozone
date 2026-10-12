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

use OZONE\Core\Testing\OZTestProject;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Recovers an account and signs up the way a user does, against a served project: the email ownership
 * is verified with the link the email carries, then the authorization it confirmed opens
 * `/account-recovery` or `/signup`, once.
 *
 * @internal
 *
 * @coversNothing
 */
final class VerificationFlowsTest extends TestCase
{
	private const DB_FILE       = 'verification_flows_test.sqlite';
	private const USER_EMAIL    = 'jane.doe@example.com';
	private const USER_PASSWORD = 'Jane_Pass_42';
	private const NEW_PASSWORD  = 'Jane_New_Pass_43';

	/** @var array<string, OZTestProject> projects by name */
	private static array $projects = [];

	/** @var array<string, Process> their servers */
	private static array $servers = [];

	/** @var array<string, int> their ports */
	private static array $ports = [];

	private static string $host = '127.0.0.1';

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		// OZone's rule: every user type proves an email or a phone.
		self::startProject('account-recovery', null);
		// A type that proves nothing next to the default rule: the route cannot demand an authorization
		// at its door, and the user type still has to present one.
		self::startProject('account-recovery-mixed', [
			'robot' => [],
			'*'     => ['auth:provider:email:verify'],
		]);
	}

	public static function tearDownAfterClass(): void
	{
		foreach (self::$servers as $server) {
			if ($server->isRunning()) {
				$server->stop(3);
			}
		}

		foreach (self::$projects as $project) {
			$project->destroy();
		}

		self::$servers  = [];
		self::$projects = [];
		self::$ports    = [];

		parent::tearDownAfterClass();
	}

	/**
	 * @return iterable<string, array{0: string}>
	 */
	public static function provideProject(): iterable
	{
		yield 'every type verifies' => ['account-recovery'];

		yield 'one type verifies nothing' => ['account-recovery-mixed'];
	}

	/**
	 * The authorization is refused while pending, accepted once the user followed the link, and the
	 * new password is the one that signs in.
	 *
	 * @dataProvider provideProject
	 */
	public function testAnAccountIsRecoveredWithAConfirmedEmailAuthorization(string $project): void
	{
		[$status, $body] = $this->request($project, 'POST', '/auth/verify/email', ['email' => self::USER_EMAIL]);

		self::assertSame(200, $status, $body);

		$ref = \json_decode($body, true)['data']['auth_ref'] ?? null;

		self::assertIsString($ref, $body);

		$recover = [
			'auth_ref'       => $ref,
			'auth_user_type' => 'user',
			'pass'           => self::NEW_PASSWORD,
			'pass_confirm'   => self::NEW_PASSWORD,
		];

		// Not confirmed yet.
		[$status, $body] = $this->request($project, 'POST', '/account-recovery', $recover);

		self::assertSame(401, $status, $body);

		// The user follows the link of the email.
		[$link_ref, $token] = self::linkOfLastEmail($project);

		self::assertSame($ref, $link_ref);

		[$status, $body] = $this->request($project, 'POST', '/auth/link/' . $link_ref . '/' . $token);

		self::assertSame(200, $status, $body);

		[$status, $body] = $this->request($project, 'POST', '/account-recovery', $recover);

		self::assertSame(200, $status, $body);
		self::assertSame('OZ_ACCOUNT_RECOVERY_SUCCESS', \json_decode($body, true)['msg'] ?? null, $body);

		// The authorization is spent: it does not reset the password a second time.
		[$status, $body] = $this->request($project, 'POST', '/account-recovery', [
			'pass'         => 'Jane_Other_Pass_44',
			'pass_confirm' => 'Jane_Other_Pass_44',
		] + $recover);

		self::assertSame(404, $status, $body);
		self::assertSame('OZ_AUTH_INVALID_OR_DELETED_REF', \json_decode($body, true)['msg'] ?? null, $body);

		[$status, $body] = $this->request($project, 'POST', '/login', [
			'auth_user_type'             => 'user',
			'auth_user_identifier_type'  => 'email',
			'auth_user_identifier_value' => self::USER_EMAIL,
			'auth_user_password'         => self::NEW_PASSWORD,
		]);

		self::assertSame(200, $status, $body);
		self::assertSame('OZ_USER_SIGN_IN_DONE', \json_decode($body, true)['msg'] ?? null, $body);
	}

	/**
	 * The account is created with the email the authorization proved, and the authorization does not
	 * create a second one.
	 */
	public function testAnAccountIsCreatedWithAConfirmedEmailAuthorization(): void
	{
		$project = 'account-recovery';
		$email   = 'john.doe@example.com';

		[$status, $body] = $this->request($project, 'POST', '/auth/verify/email', ['email' => $email]);

		self::assertSame(200, $status, $body);

		[$ref, $token] = self::linkOfLastEmail($project);

		[$status, $body] = $this->request($project, 'POST', '/auth/link/' . $ref . '/' . $token);

		self::assertSame(200, $status, $body);

		$sign_up = [
			'auth_ref'     => $ref,
			'email'        => $email,
			'civility'     => 'Mr',
			'display_name' => 'John Doe',
			'first_name'   => 'John',
			'last_name'    => 'Doe',
			'gender'       => 'Male',
			'birth_date'   => '1991-03-02',
			'pass'         => 'John_Pass_42',
			'pass_confirm' => 'John_Pass_42',
			'cc2'          => 'BJ',
		];

		[$status, $body] = $this->request($project, 'POST', '/signup', $sign_up);
		$data            = \json_decode($body, true);

		self::assertSame(200, $status, $body);
		self::assertSame('OZ_USER_SIGN_UP_SUCCESS', $data['msg'] ?? null, $body);
		self::assertSame($email, $data['data']['user_email'] ?? null, $body);

		[$status, $body] = $this->request($project, 'POST', '/signup', ['display_name' => 'John Again'] + $sign_up);

		self::assertSame(404, $status, $body);
		self::assertSame('OZ_AUTH_INVALID_OR_DELETED_REF', \json_decode($body, true)['msg'] ?? null, $body);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Creates a project with a user to recover, its emails written to a file the test reads.
	 *
	 * @param null|array<string, list<string>> $recovery_policy the `OZ_ACCOUNT_RECOVERY_VERIFICATION`
	 *                                                          rule, null for OZone's
	 */
	private static function startProject(string $name, ?array $recovery_policy): void
	{
		$project = OZTestProject::create($name, fresh: true);
		$ns      = $project->getNamespace();
		$path    = $project->getPath();

		$project->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $path . \DIRECTORY_SEPARATOR . self::DB_FILE,
		]);

		$project->writeFileFromStub('MessagingTestTransport', 'app/MessagingTestTransport.php', [
			'namespace'        => $ns,
			'deliveries_file'  => $path . '/deliveries.jsonl',
			'fail_file'        => $path . '/fail.txt',
			'unavailable_file' => $path . '/unavailable.flag',
		]);
		// Sent within the request, so the email is there when the answer is.
		$project->setSetting('oz.messaging', 'OZ_MESSAGING_CHANNELS', [
			'mail' => ['transport' => "{$ns}\\MessagingTestTransport", 'async' => false],
		]);

		if (null !== $recovery_policy) {
			$project->setSetting('oz.auth.verification', 'OZ_ACCOUNT_RECOVERY_VERIFICATION', $recovery_policy);
		}

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

		self::$projects[$name] = $project;
		self::$servers[$name]  = $server;
		self::$ports[$name]    = $port;
	}

	/**
	 * The reference and token of the authorization link in the last email sent.
	 *
	 * @return array{0: string, 1: string}
	 */
	private static function linkOfLastEmail(string $project): array
	{
		$file = self::$projects[$project]->getPath() . '/deliveries.jsonl';

		self::assertFileExists($file, 'No email was sent.');

		$lines = \array_filter(\explode("\n", (string) \file_get_contents($file)));
		$last  = \json_decode((string) \end($lines), true);
		$text  = \is_array($last) ? (string) ($last['text'] ?? '') : '';

		self::assertMatchesRegularExpression('~/auth/link/[^/\s]+/[^/\s]+~', $text);
		\preg_match('~/auth/link/([^/\s]+)/([^/\s]+)~', $text, $m);

		return [$m[1], $m[2]];
	}

	/**
	 * Sends a request to the project's server.
	 *
	 * @param array<string, string> $fields
	 *
	 * @return array{0: int, 1: string}
	 */
	private function request(string $project, string $method, string $path, array $fields = []): array
	{
		$url = 'http://' . self::$host . ':' . self::$ports[$project] . $path;

		$opts = [
			'method'          => $method,
			'timeout'         => 10,
			'ignore_errors'   => true,
			'follow_location' => false,
			'header'          => "Accept: application/json\r\n",
		];

		if ([] !== $fields) {
			$opts['content'] = \http_build_query($fields);
			$opts['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
		}

		$ctx  = \stream_context_create(['http' => $opts]);
		$body = @\file_get_contents($url, false, $ctx);

		if (false === $body) {
			return [0, ''];
		}

		$status = 0;

		if (
			!empty($http_response_header[0])
			&& \preg_match('/HTTP\/\d+(?:\.\d+)? (\d+)/', $http_response_header[0], $m)
		) {
			$status = (int) $m[1];
		}

		return [$status, $body];
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
