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
use OZONE\Core\Auth\AuthUsersRepository;
use OZONE\Core\Auth\VerificationPolicy;
use OZONE\Core\Testing\OZTestProject;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * A project with no `oz_users`: its auth users are all of its own `admins` table (type `admin`),
 * which it names as the default type. The schema and the migrations leave `oz_users` out, an admin is
 * added and made super admin by `oz auth-users` with no type named, signs in, and a visitor signs up
 * as one with no type named either; the doctor says what the default type is, and fails a default
 * no repository serves.
 *
 * @internal
 */
#[CoversNothing]
final class WithoutDefaultAuthUserTableTest extends TestCase
{
	private const string HOST = '127.0.0.1';

	private const string DB_FILE = 'without_default_table_test.sqlite';

	private const string PASSWORD = 'Admin_Pass_42';

	private static ?OZTestProject $proj = null;

	/** @var null|array{0: Process, 1: string, 2: int} */
	private static ?array $server = null;

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$proj = OZTestProject::create('without-default-table', fresh: true);

		$proj->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $proj->getPath() . '/' . self::DB_FILE,
		]);

		$ns = $proj->getNamespace();

		$proj->writeFileFromStub('AuthUserPictureAdmins', 'app/AuthUserPictureAdmins.php', ['namespace' => $ns]);
		$proj->setSetting('oz.boot', "{$ns}\\AuthUserPictureAdmins", true);
		$proj->setSetting('oz.auth.users.repositories', 'admin', AuthUsersRepository::class);
		$proj->setSetting('oz.auth.users', 'OZ_AUTH_USER_DEFAULT_TABLE', false);
		$proj->setSetting('oz.auth.users', 'OZ_AUTH_USER_DEFAULT_TYPE', 'admin');
		$proj->setSetting('oz.auth.users', 'OZ_AUTH_USER_SIGN_UP_TYPES', ['admin']);
		// Nothing to prove before signing up, so the type is what the test is about.
		$proj->setSetting('oz.auth.verification', 'OZ_SIGNUP_VERIFICATION', [VerificationPolicy::ANY_USER_TYPE => []]);

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		self::$proj = $proj;

		self::seedCountry();
	}

	#[Override]
	public static function tearDownAfterClass(): void
	{
		self::stopServer();
		self::$proj?->destroy();
		self::$proj = null;

		parent::tearDownAfterClass();
	}

	public function testTheSchemaHasNoDefaultTableAndTheProjectsOwnIsTheDefault(): void
	{
		self::assertNull(self::tableOrNull('oz_users'));
		self::assertNotNull(self::tableOrNull('admins'));
		// The usernames of every type stay.
		self::assertNotNull(self::tableOrNull('oz_usernames'));

		$doctor = self::proj()->oz('doctor', 'check');
		$doctor->run();

		self::assertMatchesRegularExpression('~Default auth user type\s+│\s+OK\s+│\s+"admin" \(admins\)~u', $doctor->getOutput());
	}

	public function testAnAdminIsAddedGrantedAndSignsInWithNoTypeNamed(): void
	{
		$email = 'first.admin@example.com';

		self::add($email);
		self::proj()->oz('auth-users', 'grant', '--user=' . $email, '--role=super-admin')->mustRun();

		$doctor = self::proj()->oz('doctor', 'check');
		$doctor->run();

		self::assertMatchesRegularExpression('~Super admin\s+│\s+OK\s+│\s+present~u', $doctor->getOutput());

		self::startServer();

		[$status, $body] = self::post('/login', [
			'auth_user_type'             => 'admin',
			'auth_user_identifier_type'  => 'email',
			'auth_user_identifier_value' => $email,
			'auth_user_password'         => self::PASSWORD,
		]);

		self::assertSame(200, $status, $body);
	}

	public function testAVisitorSignsUpAsTheDefaultTypeWithNoTypeNamed(): void
	{
		self::startServer();

		[$status, $body] = self::post('/signup', [
			'email'        => 'signed.up@example.com',
			'civility'     => 'Mx',
			'display_name' => 'Signed Up',
			'first_name'   => 'Signed',
			'last_name'    => 'Up',
			'gender'       => 'None',
			'birth_date'   => '1991-03-02',
			'pass'         => self::PASSWORD,
			'pass_confirm' => self::PASSWORD,
			'cc2'          => 'BJ',
		]);

		$data = \json_decode($body, true);

		self::assertSame(200, $status, $body);
		self::assertSame('signed.up@example.com', $data['data']['admin_email'] ?? null, $body);
	}

	public function testTheDoctorFailsADefaultTypeNoRepositoryServes(): void
	{
		self::proj()->setSetting('oz.auth.users', 'OZ_AUTH_USER_DEFAULT_TYPE', 'user');

		try {
			$doctor = self::proj()->oz('doctor', 'check');
			$doctor->run();

			self::assertSame(1, $doctor->getExitCode(), $doctor->getOutput());
			self::assertMatchesRegularExpression('~Default auth user type\s+│\s+FAIL\s+│~u', $doctor->getOutput());
			self::assertStringContainsString('OZ_AUTH_USER_DEFAULT_TABLE', $doctor->getOutput());
		} finally {
			self::proj()->setSetting('oz.auth.users', 'OZ_AUTH_USER_DEFAULT_TYPE', 'admin');
		}
	}

	private static function add(string $email): void
	{
		self::proj()->oz(
			'auth-users',
			'add',
			'--civility=Mx',
			'--display_name=First Admin',
			'--first_name=First',
			'--last_name=Admin',
			'--email=' . $email,
			'--gender=None',
			'--birth_date=1990-05-17',
			'--pass=' . self::PASSWORD,
			'--cc2=BJ',
		)->mustRun();
	}

	private static function startServer(): void
	{
		if (null === self::$server) {
			try {
				self::$server = self::proj()->startServer('api', self::HOST);
			} catch (RuntimeException $e) {
				self::fail($e->getMessage());
			}
		}
	}

	private static function stopServer(): void
	{
		if (null !== self::$server && self::$server[0]->isRunning()) {
			self::$server[0]->stop(3);
		}

		self::$server = null;
	}

	/**
	 * @param array<string, string> $fields
	 *
	 * @return array{0: int, 1: string}
	 */
	private static function post(string $path, array $fields): array
	{
		self::assertNotNull(self::$server, 'The server is not running.');

		[, $host, $port] = self::$server;

		$body  = @\file_get_contents("http://{$host}:{$port}{$path}", false, \stream_context_create(['http' => [
			'method'        => 'POST',
			'timeout'       => 20,
			'ignore_errors' => true,
			'header'        => "Accept: application/json\r\nContent-Type: application/x-www-form-urlencoded\r\n",
			'content'       => \http_build_query($fields),
		]]));
		$lines = \http_get_last_response_headers() ?? [];

		$status = isset($lines[0]) && \preg_match('~HTTP/\d+(?:\.\d+)? (\d+)~', $lines[0], $m) ? (int) $m[1] : 0;

		return [$status, false === $body ? '' : $body];
	}

	private static function proj(): OZTestProject
	{
		$project = self::$proj;

		self::assertNotNull($project, 'The test project was not created.');

		return $project;
	}

	private static function pdo(): PDO
	{
		return new PDO(
			'sqlite:' . self::proj()->getPath() . '/' . self::DB_FILE,
			null,
			null,
			[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
		);
	}

	/**
	 * A table's name, a generated project prefixing them at random; null when there is none.
	 */
	private static function tableOrNull(string $name): ?string
	{
		$stmt = self::pdo()->prepare(
			"SELECT name FROM sqlite_master WHERE type = 'table' AND (name = ? OR name LIKE ? ESCAPE '\\')"
		);
		$stmt->execute([$name, '%\\_' . $name]);

		$table = $stmt->fetchColumn();

		return \is_string($table) ? $table : null;
	}

	/**
	 * Adds the country an auth user needs (`*_cc2`): no command creates one.
	 */
	private static function seedCountry(): void
	{
		$now = (string) \time();

		self::pdo()->prepare(\sprintf(
			'INSERT OR IGNORE INTO "%s" (country_cc2, country_calling_code, country_name, country_name_real,'
				. ' country_data, country_is_valid, country_created_at, country_updated_at, country_deleted,'
				. ' country_deleted_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?, 0, NULL)',
			self::tableOrNull('oz_countries') ?? 'oz_countries'
		))->execute(['BJ', '+229', 'Benin', 'Benin', '{}', $now, $now]);
	}
}
