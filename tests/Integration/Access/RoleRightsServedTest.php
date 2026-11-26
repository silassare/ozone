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

namespace OZONE\Tests\Integration\Access;

use Override;
use OZONE\Core\Testing\OZTestProject;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * A role given a right, over HTTP: a table opened to administrators only, an editor refused, a super
 * admin giving the editor role the right to create in it, the editor then creating and reading the
 * right as theirs, and refused again once the role is refused it. Only a super admin changes a
 * role's rights.
 *
 * @internal
 */
#[CoversNothing]
final class RoleRightsServedTest extends TestCase
{
	private const string HOST = '127.0.0.1';

	private const string DB_FILE = 'role_rights_test.sqlite';

	private const string PASSWORD = 'Rights_Pass_42';

	private static ?OZTestProject $proj = null;

	/** @var null|array{0: Process, 1: string, 2: int} */
	private static ?array $server = null;

	/** @var array<string, list<string>> the session headers, by user */
	private static array $sessions = [];

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$proj = OZTestProject::create('role-rights', fresh: true);

		$proj->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $proj->getPath() . '/' . self::DB_FILE,
		]);

		$ns = $proj->getNamespace();

		$proj->setSetting('oz.db.schema', 'notes', [
			'plural_name'   => 'notes',
			'singular_name' => 'note',
			'column_prefix' => 'note',
			'columns'       => [
				'id'    => ['type' => 'bigint', 'auto_increment' => true, 'unsigned' => true],
				'title' => ['type' => 'string', 'max' => 60],
			],
			'constraints'   => [['type' => 'primary_key', 'columns' => ['id']]],
		]);
		$proj->writeFileFromStub('TableRESTService', 'app/Notes/TableRESTService.php', [
			'namespace' => "{$ns}\\Notes",
			'table'     => 'notes',
		]);
		$proj->setSetting('oz.routes.api', "{$ns}\\Notes\\TableRESTService", true);
		$proj->writeFileFromStub('AdminOnlyCrudHandler', 'app/AdminOnlyCrudHandler.php', [
			'namespace' => $ns,
			'table'     => 'notes',
		]);
		$proj->setSetting('oz.gobl.crud', "{$ns}\\AdminOnlyCrudHandler", true);

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();
		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();

		self::$proj = $proj;

		self::seedCountry();

		try {
			self::$server = $proj->startServer('api', self::HOST);
		} catch (RuntimeException $e) {
			self::fail($e->getMessage());
		}

		foreach (['super' => 'super-admin', 'admin' => 'admin', 'editor' => 'editor'] as $name => $role) {
			$email = $name . '@example.com';

			self::add($email);
			$proj->oz('auth-users', 'grant', '--user=' . $email, '--role=' . $role)->mustRun();
			self::$sessions[$name] = self::signIn($email);
		}
	}

	#[Override]
	public static function tearDownAfterClass(): void
	{
		if (null !== self::$server && self::$server[0]->isRunning()) {
			self::$server[0]->stop(3);
		}

		self::$server = null;
		self::$proj?->destroy();
		self::$proj   = null;

		parent::tearDownAfterClass();
	}

	public function testARoleGivenARightLetsItsHoldersInUntilItIsRefused(): void
	{
		[$status] = self::request('POST', '/notes', ['title' => 'before'], 'editor');

		self::assertSame(403, $status);

		// The matrix, read by an administrator: the notes' create right is listed.
		[$status, $matrix] = self::request('GET', '/access-rights/roles', [], 'admin');

		self::assertSame(200, $status, \json_encode($matrix));

		$create = null;

		foreach ($matrix['data']['actions'] ?? [] as $action) {
			if (\str_ends_with((string) ($action['action'] ?? ''), '.create') && \str_starts_with((string) $action['action'], 'note')) {
				$create = (string) $action['action'];
			}
		}

		self::assertNotNull($create, \json_encode($matrix));
		self::assertFalse($matrix['data']['editable'] ?? null, 'an administrator does not change a role');

		// A super admin reads it too, and may change it.
		[$status, $as_super] = self::request('GET', '/access-rights/roles', [], 'super');

		self::assertSame(200, $status, \json_encode($as_super));
		self::assertTrue($as_super['data']['editable'] ?? null);

		// Not an administrator's to change: super admins only.
		[$status] = self::request('POST', '/access-rights/roles', ['role' => 'editor', 'allow_actions' => [$create]], 'admin');

		self::assertSame(403, $status);

		[$status, $json] = self::request('POST', '/access-rights/roles', ['role' => 'editor', 'allow_actions' => [$create]], 'super');

		self::assertSame(200, $status, \json_encode($json));
		self::assertTrue($json['data']['grants']['editor'][$create] ?? null, \json_encode($json));

		[$status, $json] = self::request('POST', '/notes', ['title' => 'by role'], 'editor');

		self::assertSame(201, $status, \json_encode($json));

		[, $mine] = self::request('GET', '/access-rights/me', [], 'editor');

		self::assertTrue($mine['data']['list'][$create]['allowed'] ?? null, \json_encode($mine));

		[$status] = self::request('POST', '/access-rights/roles', ['role' => 'editor', 'deny_actions' => [$create]], 'super');

		self::assertSame(200, $status);

		[$status] = self::request('POST', '/notes', ['title' => 'after'], 'editor');

		self::assertSame(403, $status);
	}

	public function testAnActionNoTableHasIsRefused(): void
	{
		[$status] = self::request('POST', '/access-rights/roles', ['role' => 'editor', 'allow_actions' => ['nothing.at_all']], 'super');

		self::assertSame(400, $status);
	}

	private static function add(string $email): void
	{
		self::proj()->oz(
			'auth-users',
			'add',
			'--civility=Mx',
			'--display_name=' . \strstr($email, '@', true),
			'--first_name=Role',
			'--last_name=Rights',
			'--email=' . $email,
			'--gender=None',
			'--birth_date=1990-05-17',
			'--pass=' . self::PASSWORD,
			'--cc2=BJ',
		)->mustRun();
	}

	/**
	 * Signs a user in: the headers of their session.
	 *
	 * @return list<string>
	 */
	private static function signIn(string $email): array
	{
		[$status, $json, $headers] = self::send('POST', '/login', \http_build_query([
			'auth_user_type'             => 'user',
			'auth_user_identifier_type'  => 'email',
			'auth_user_identifier_value' => $email,
			'auth_user_password'         => self::PASSWORD,
		]), ['Content-Type: application/x-www-form-urlencoded']);

		self::assertSame(200, $status, \json_encode($json));

		$cookies = [];

		foreach ($headers as $header) {
			if (\preg_match('~^Set-Cookie:\s*([^=;]+)=([^;]*)~i', $header, $m)) {
				$cookies[\urldecode($m[1])] = \urldecode($m[2]);
			}
		}

		return [
			'Cookie: OZONE_SID=' . ($cookies['OZONE_SID'] ?? ''),
			'X-XSRF-TOKEN: ' . ($cookies['XSRF-TOKEN'] ?? ''),
		];
	}

	/**
	 * A request as a signed-in user, its body as JSON.
	 *
	 * @param array<string, mixed> $body
	 *
	 * @return array{0: int, 1: array<string, mixed>}
	 */
	private static function request(string $method, string $path, array $body, string $as): array
	{
		[$status, $json] = self::send(
			$method,
			$path,
			[] === $body ? null : \json_encode($body, \JSON_THROW_ON_ERROR),
			[...self::$sessions[$as], 'Content-Type: application/json']
		);

		return [$status, $json];
	}

	/**
	 * @param list<string> $headers
	 *
	 * @return array{0: int, 1: array<string, mixed>, 2: list<string>}
	 */
	private static function send(string $method, string $path, ?string $content, array $headers): array
	{
		self::assertNotNull(self::$server, 'The server is not running.');

		[, $host, $port] = self::$server;

		$opts = [
			'method'        => $method,
			'timeout'       => 20,
			'ignore_errors' => true,
			'header'        => "Accept: application/json\r\n"
				. \implode('', \array_map(static fn (string $h): string => $h . "\r\n", $headers)),
		];

		if (null !== $content) {
			$opts['content'] = $content;
		}

		$body  = @\file_get_contents("http://{$host}:{$port}{$path}", false, \stream_context_create(['http' => $opts]));
		$lines = \http_get_last_response_headers() ?? [];

		$status = isset($lines[0]) && \preg_match('~HTTP/\d+(?:\.\d+)? (\d+)~', $lines[0], $m) ? (int) $m[1] : 0;

		return [$status, (array) \json_decode(false === $body ? '' : $body, true), $lines];
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
	 * Adds the country a user needs (`user_cc2`): no command creates one.
	 */
	private static function seedCountry(): void
	{
		$pdo  = self::pdo();
		$stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE ?");
		$stmt->execute(['%oz_countries']);

		$table = (string) $stmt->fetchColumn();
		$now   = (string) \time();

		$stmt->closeCursor();

		$pdo->prepare(\sprintf(
			'INSERT OR IGNORE INTO "%s" (country_cc2, country_calling_code, country_name, country_name_real,'
				. ' country_data, country_is_valid, country_created_at, country_updated_at, country_deleted,'
				. ' country_deleted_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?, 0, NULL)',
			$table
		))->execute(['BJ', '+229', 'Benin', 'Benin', '{}', $now, $now]);
	}
}
