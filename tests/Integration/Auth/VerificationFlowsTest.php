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
	 * Who started the recovery recovers with the code the email carries, typed where they asked: the
	 * authorization is refused while pending, works once confirmed, and only once.
	 *
	 * @dataProvider provideAnAccountIsRecoveredWithTheCodeOfTheEmailCases
	 */
	public function testAnAccountIsRecoveredWithTheCodeOfTheEmail(string $project): void
	{
		$ref = $this->startVerification($project, self::USER_EMAIL, 'account-recovery');

		$recover = [
			'auth_ref'       => $ref,
			'auth_user_type' => 'user',
			'pass'           => self::NEW_PASSWORD,
			'pass_confirm'   => self::NEW_PASSWORD,
		];

		// Not confirmed yet.
		[$status, $body] = $this->request($project, 'POST', '/account-recovery', $recover);

		self::assertSame(401, $status, $body);

		$text = self::lastMessage($project)['text'];

		self::assertStringContainsString('Reset your password', $text);
		self::assertMatchesRegularExpression('~code (\d+)~', $text);
		\preg_match('~code (\d+)~', $text, $m);

		[$status, $body] = $this->request($project, 'POST', '/auth/' . $ref . '/authorize', ['code' => $m[1]]);

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

		$this->assertSignsIn($project, self::NEW_PASSWORD);
	}

	/**
	 * @return iterable<string, array{0: string}>
	 */
	public static function provideAnAccountIsRecoveredWithTheCodeOfTheEmailCases(): iterable
	{
		yield 'every type verifies' => ['account-recovery'];

		yield 'one type verifies nothing' => ['account-recovery-mixed'];
	}

	/**
	 * The link of the email sets the new password on its own page, and grants nothing to whoever
	 * started the recovery: their reference is gone.
	 */
	public function testTheLinkOfARecoverySetsThePasswordWhereItIsOpened(): void
	{
		$project = 'account-recovery';
		$ref     = $this->startVerification($project, self::USER_EMAIL, 'account-recovery');

		[$link_ref, $token] = self::linkOfLastMessage($project);
		$link               = '/auth/link/' . $link_ref . '/' . $token;

		[$status, $body] = $this->request($project, 'GET', $link);

		self::assertSame(200, $status, $body);
		self::assertStringContainsString('name="pass_confirm"', $body);
		self::assertStringContainsString(self::USER_EMAIL, $body);

		// Two passwords that differ: the form again, the link still usable.
		[$status, $body] = $this->request($project, 'POST', $link, [
			'pass'         => 'Jane_Link_Pass_45',
			'pass_confirm' => 'Jane_Link_Pass_46',
		]);

		self::assertSame(400, $status, $body);
		self::assertStringContainsString('The two passwords are not the same.', $body);

		[$status, $body] = $this->request($project, 'POST', $link, [
			'pass'         => 'Jane_Link_Pass_45',
			'pass_confirm' => 'Jane_Link_Pass_45',
		]);

		self::assertSame(200, $status, $body);
		self::assertStringContainsString('Password changed', $body);

		// Who started it holds a reference to nothing.
		[$status, $body] = $this->request($project, 'POST', '/account-recovery', [
			'auth_ref'       => $ref,
			'auth_user_type' => 'user',
			'pass'           => 'Attacker_Pass_47',
			'pass_confirm'   => 'Attacker_Pass_47',
		]);

		self::assertSame(404, $status, $body);

		[$status] = $this->request($project, 'POST', $link, [
			'pass'         => 'Jane_Link_Pass_48',
			'pass_confirm' => 'Jane_Link_Pass_48',
		]);

		self::assertSame(404, $status, 'The link worked twice.');

		$this->assertSignsIn($project, 'Jane_Link_Pass_45');
	}

	/**
	 * A verification started to check an address does not recover the account of that address, even
	 * confirmed by its owner, whose page did not say it would.
	 */
	public function testAVerificationStartedForSomethingElseDoesNotRecover(): void
	{
		$project = 'account-recovery';
		$ref     = $this->startVerification($project, self::USER_EMAIL, null);

		[$link_ref, $token] = self::linkOfLastMessage($project);

		[$status, $body] = $this->request($project, 'GET', '/auth/link/' . $link_ref . '/' . $token);

		self::assertSame(200, $status, $body);
		self::assertStringContainsString('is yours', $body);

		[$status, $body] = $this->request($project, 'POST', '/auth/link/' . $link_ref . '/' . $token);

		self::assertSame(200, $status, $body);

		[$status, $body] = $this->request($project, 'POST', '/account-recovery', [
			'auth_ref'       => $ref,
			'auth_user_type' => 'user',
			'pass'           => 'Attacker_Pass_47',
			'pass_confirm'   => 'Attacker_Pass_47',
		]);

		self::assertSame(403, $status, $body);
		self::assertSame('OZ_AUTH_WRONG_PURPOSE', \json_decode($body, true)['msg'] ?? null, $body);
	}

	/**
	 * The account is created with the email the authorization proved, and the authorization does not
	 * create a second one.
	 */
	public function testAnAccountIsCreatedWithAConfirmedEmailAuthorization(): void
	{
		$project = 'account-recovery';
		$email   = 'john.doe@example.com';

		$this->startVerification($project, $email, 'sign-up');

		self::assertStringContainsString('Create your account', self::lastMessage($project)['text']);

		[$ref, $token] = self::linkOfLastMessage($project);

		[$status, $body] = $this->request($project, 'GET', '/auth/link/' . $ref . '/' . $token);

		self::assertSame(200, $status, $body);
		self::assertStringContainsString('Confirming lets the account started with ' . $email, $body);

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

	/**
	 * The SMS says what its code is for.
	 */
	public function testTheSmsOfAVerificationSaysWhatItIsFor(): void
	{
		$project = 'account-recovery';

		foreach (['check' => 'verification code', 'sign-up' => 'create an account', 'account-recovery' => 'reset your password'] as $purpose => $says) {
			[$status, $body] = $this->request($project, 'POST', '/auth/verify/phone', [
				'phone'   => '+22997000000',
				'purpose' => $purpose,
			]);

			self::assertSame(200, $status, $body);
			self::assertStringContainsString($says, self::lastMessage($project)['text'], $purpose);
		}

		[$status, $body] = $this->request($project, 'POST', '/auth/verify/phone', [
			'phone'   => '+22997000000',
			'purpose' => 'anything',
		]);

		self::assertSame(400, $status, $body);
	}

	/**
	 * A link whose authorization expired says so when it is opened, rather than offering a button, or
	 * a password form, that could only fail. The row is still there: the garbage collection deletes it
	 * an hour later.
	 *
	 * @dataProvider provideExpiredLinkCases
	 */
	public function testAnExpiredLinkSaysSoBeforeAnythingIsTyped(string $purpose): void
	{
		$project = 'account-recovery';

		$this->startVerification($project, self::USER_EMAIL, $purpose);

		[$ref, $token] = self::linkOfLastMessage($project);

		self::expire($project, $ref);

		[$status, $body] = $this->request($project, 'GET', '/auth/link/' . $ref . '/' . $token);

		self::assertSame(401, $status, $body);
		self::assertStringContainsString('This link no longer works', $body);
		self::assertStringNotContainsString('name="pass"', $body);
	}

	/**
	 * @return iterable<string, array{0: string}>
	 */
	public static function provideExpiredLinkCases(): iterable
	{
		yield 'a sign-up' => ['sign-up'];

		yield 'an account recovery' => ['account-recovery'];
	}

	/**
	 * A link already answered says the same: it is not offered twice.
	 */
	public function testALinkAlreadyAnsweredSaysItNoLongerWorks(): void
	{
		$project = 'account-recovery';

		$this->startVerification($project, 'claire.doe@example.com', 'sign-up');

		[$ref, $token] = self::linkOfLastMessage($project);
		$link          = '/auth/link/' . $ref . '/' . $token;

		[$status, $body] = $this->request($project, 'POST', $link);

		self::assertSame(200, $status, $body);

		[$status, $body] = $this->request($project, 'GET', $link);

		self::assertSame(401, $status, $body);
		self::assertStringContainsString('This link no longer works', $body);
	}

	/**
	 * Back-dates the expiry of an authorization, as waiting would.
	 */
	private static function expire(string $project, string $ref): void
	{
		$pdo   = self::pdo($project);
		$table = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE '%oz_auths'")
			->fetchColumn();

		self::assertIsString($table);

		$statement = $pdo->prepare(
			\sprintf('UPDATE "%s" SET auth_expire_at = ? WHERE auth_ref = ?', $table)
		);
		$statement->execute([(string) (\time() - 1), $ref]);

		self::assertSame(1, $statement->rowCount(), 'No authorization was aged.');
	}

	/**
	 * A connection to a project's database.
	 */
	private static function pdo(string $project): PDO
	{
		return new PDO(
			'sqlite:' . self::$projects[$project]->getPath() . \DIRECTORY_SEPARATOR . self::DB_FILE,
			null,
			null,
			[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
		);
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
			'sms'  => ['transport' => "{$ns}\\MessagingTestTransport", 'async' => false],
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
	 * Starts an email verification and returns its reference.
	 *
	 * @param null|string $purpose null to send none
	 */
	private function startVerification(string $project, string $email, ?string $purpose): string
	{
		$fields = ['email' => $email];

		if (null !== $purpose) {
			$fields['purpose'] = $purpose;
		}

		[$status, $body] = $this->request($project, 'POST', '/auth/verify/email', $fields);

		self::assertSame(200, $status, $body);

		$ref = \json_decode($body, true)['data']['auth_ref'] ?? null;

		self::assertIsString($ref, $body);

		return $ref;
	}

	/**
	 * Signs the user in with a password.
	 */
	private function assertSignsIn(string $project, string $password): void
	{
		[$status, $body] = $this->request($project, 'POST', '/login', [
			'auth_user_type'             => 'user',
			'auth_user_identifier_type'  => 'email',
			'auth_user_identifier_value' => self::USER_EMAIL,
			'auth_user_password'         => $password,
		]);

		self::assertSame(200, $status, $body);
		self::assertSame('OZ_USER_SIGN_IN_DONE', \json_decode($body, true)['msg'] ?? null, $body);
	}

	/**
	 * The last message sent, email or SMS.
	 *
	 * @return array{text: string}
	 */
	private static function lastMessage(string $project): array
	{
		$file = self::$projects[$project]->getPath() . '/deliveries.jsonl';

		self::assertFileExists($file, 'No email was sent.');

		$lines = \array_filter(\explode("\n", (string) \file_get_contents($file)));
		$last  = \json_decode((string) \end($lines), true);

		return ['text' => \is_array($last) ? (string) ($last['text'] ?? '') : ''];
	}

	/**
	 * The reference and token of the authorization link in the last email sent.
	 *
	 * @return array{0: string, 1: string}
	 */
	private static function linkOfLastMessage(string $project): array
	{
		$text = self::lastMessage($project)['text'];

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
