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

namespace OZONE\Tests\Integration\Users;

use Override;
use OZONE\Core\Testing\OZTestProject;
use OZONE\Core\Users\Picture\PublicPictureAccess;
use OZONE\Core\Users\Picture\SelfOrAdminPictureAccess;
use OZONE\Tests\Support\ImageBytes;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * A user's picture, fetched as a browser would from a served project: signed in by a real session,
 * the picture uploaded through `/upload` and set on the user, and `oz:user-picture` answering to
 * whom the rule allows, as `oz:files` would, with what the project answers in its place.
 *
 * @internal
 */
#[CoversNothing]
final class UserPictureServedTest extends TestCase
{
	private const string HOST = '127.0.0.1';

	private const string DB_FILE = 'user_picture_test.sqlite';

	private static ?OZTestProject $proj = null;

	/** @var null|array{0: Process, 1: string, 2: int} */
	private static ?array $server = null;

	/**
	 * The users, by name: their id, their email and the headers of their session.
	 *
	 * @var array<string, array{id: string, email: string, session: list<string>}>
	 */
	private static array $users = [];

	/**
	 * The picture's file, as `/upload` answered it.
	 *
	 * @var array<string, mixed>
	 */
	private static array $file = [];

	private static string $photo = '';

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$proj = OZTestProject::create('user-picture', fresh: true);

		$proj->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $proj->getPath() . '/' . self::DB_FILE,
		]);

		$ns = $proj->getNamespace();

		$proj->writeFileFromStub('UserPictureFriendsAccess', 'app/UserPictureFriendsAccess.php', ['namespace' => $ns]);
		$proj->writeFileFromStub('UserPictureInitials', 'app/UserPictureInitials.php', ['namespace' => $ns]);
		$proj->writeFile('app/files/refused.png', ImageBytes::filled(100, 100, 0, 0, 255));

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		self::$proj = $proj;

		try {
			self::$server = $proj->startServer('api', self::HOST);
		} catch (RuntimeException $e) {
			$proj->destroy();
			self::$proj = null;
			self::fail($e->getMessage());
		}

		self::seedCountry();

		$domains = [
			'owner'     => 'example.com',
			'colleague' => 'example.com',
			'admin'     => 'example.org',
			'other'     => 'example.net',
			'bare'      => 'example.com',
		];

		foreach ($domains as $name => $domain) {
			self::$users[$name] = self::signUpAndIn($name, $name . '@' . $domain);
		}

		$proj->oz('users', 'grant', '--user=' . self::$users['admin']['email'], '--role=admin')->mustRun();

		// The owner's picture: a photo with what the camera wrote, uploaded, then set on the user.
		self::$photo = ImageBytes::withExif(ImageBytes::jpeg(200, 100), 1);

		[$status, $body] = self::multipart('/upload/', [
			['name' => 'files[]', 'filename' => 'me.jpg', 'type' => 'image/jpeg', 'content' => self::$photo],
		], self::$users['owner']['session']);

		self::assertSame(200, $status, $body);

		self::$file = \json_decode($body, true)['data']['files'][0] ?? [];

		self::assertNotEmpty(self::$file['file_id'] ?? null, $body);

		self::pdo()->prepare(\sprintf('UPDATE "%s" SET user_pic = ? WHERE user_id = ?', self::table('oz_users')))
			->execute([self::$file['file_id'], self::$users['owner']['id']]);
	}

	#[Override]
	public static function tearDownAfterClass(): void
	{
		self::stopServer();
		self::$proj?->destroy();
		self::$proj = null;

		parent::tearDownAfterClass();
	}

	public function testTheDefaultRuleServesTheUserAndAdministratorsOnly(): void
	{
		$path = self::picturePath('owner');

		foreach (['owner', 'admin'] as $viewer) {
			[$status, $body, $headers] = self::get($path, self::$users[$viewer]['session']);

			self::assertSame(200, $status, $viewer . ': ' . $body);
			self::assertSame([200, 100], ImageBytes::size($body), $viewer);
			self::assertSame('image/jpeg', $headers['content-type'] ?? null);
			// What the camera wrote stays out of a picture.
			self::assertStringNotContainsString('OZoneCamera', $body);
		}

		[$status] = self::get($path, self::$users['other']['session']);

		self::assertSame(403, $status);

		[$status] = self::get($path);

		self::assertSame(403, $status);
	}

	public function testTheAnswerIsPrivateAndRevalidatedByItsETag(): void
	{
		$session                    = self::$users['owner']['session'];
		[$status, $body, $headers]  = self::get(self::picturePath('owner', 'w64'), $session);

		self::assertSame(200, $status, $body);
		self::assertSame('private, no-cache', $headers['cache-control'] ?? null);
		self::assertNotEmpty($headers['etag'] ?? null);
		// No file key anywhere in what a viewer receives.
		self::assertStringNotContainsString((string) self::$file['file_key'], \implode("\n", $headers));

		[$status, $body] = self::get(self::picturePath('owner', 'w64'), [
			...$session,
			'If-None-Match: ' . $headers['etag'],
		]);

		self::assertSame(304, $status);
		self::assertSame('', $body);
	}

	public function testThePictureIsWhatOzFilesServesForTheSameTokens(): void
	{
		$file = \sprintf('/files/ozone-%s-%s', self::$file['file_id'], self::$file['file_key']);

		foreach (['' => $file, 'w64' => $file . '-w64', 'thumb48~grayscale' => $file . '-thumb48~grayscale'] as $tokens => $url) {
			[$status, $picture] = self::get(self::picturePath('owner', $tokens), self::$users['owner']['session']);

			self::assertSame(200, $status, $picture);
			self::assertSame(self::get($url)[1], $picture, $tokens);
		}
	}

	public function testTheOriginalIsNotServedEvenToTheUser(): void
	{
		[$status] = self::get(self::picturePath('owner', 'original'), self::$users['owner']['session']);

		self::assertSame(403, $status);

		// Where `oz:files` serves it to its uploader.
		[$status, $original] = self::get(
			\sprintf('/files/ozone-%s-%s-original', self::$file['file_id'], self::$file['file_key']),
			self::$users['owner']['session']
		);

		self::assertSame(200, $status);
		self::assertSame(self::$photo, $original);
	}

	public function testNoPictureIsNotFoundOnlyToAViewerTheRuleAllows(): void
	{
		[$status] = self::get(self::picturePath('bare'), self::$users['bare']['session']);

		self::assertSame(404, $status);

		[$status] = self::get(self::picturePath('bare'), self::$users['other']['session']);

		self::assertSame(403, $status);

		// An id no user has: refused as well, so a refused viewer learns nothing of who exists.
		[$status] = self::get('/users/999999/picture', self::$users['other']['session']);

		self::assertSame(403, $status);
	}

	public function testPublicLetsAnyoneSeeIt(): void
	{
		self::configure(['OZ_USER_PICTURE_ACCESS' => PublicPictureAccess::class]);

		try {
			[$status, $body] = self::get(self::picturePath('owner'));

			self::assertSame(200, $status, $body);
			self::assertSame([200, 100], ImageBytes::size($body));
		} finally {
			self::configure(['OZ_USER_PICTURE_ACCESS' => SelfOrAdminPictureAccess::class]);
		}
	}

	public function testAProjectsRuleFallbacksAndForcedTokens(): void
	{
		$ns = self::proj()->getNamespace();

		self::configure([
			'OZ_USER_PICTURE_ACCESS'   => $ns . '\UserPictureFriendsAccess',
			'OZ_USER_PICTURE_TOKENS'   => ['w64'],
			'OZ_USER_PICTURE_FALLBACK' => [
				'refused' => 'app/files/refused.png',
				'missing' => $ns . '\UserPictureInitials',
			],
		]);

		try {
			// A colleague: the picture, capped at the forced size whatever the link asks.
			[$status, $body, $headers] = self::get(self::picturePath('owner', 'w640'), self::$users['colleague']['session']);

			self::assertSame(200, $status, $body);
			self::assertSame([64, 32], ImageBytes::size($body));
			self::assertSame('private, no-cache', $headers['cache-control'] ?? null);

			// The project's rule is the whole rule: an administrator of another domain is refused,
			// and answered the project's image, rendered as a picture.
			[$status, $body] = self::get(self::picturePath('owner', 'w32'), self::$users['admin']['session']);

			self::assertSame(200, $status, $body);
			self::assertSame([32, 32], ImageBytes::size($body));
			self::assertSame([0, 0, 255], \array_slice(ImageBytes::rgbAt($body, 16, 16), 0, 3));

			// Nobody signed in: the same image, saying nothing of whether there is a picture.
			[$status, $refused] = self::get(self::picturePath('bare', 'w32'));

			self::assertSame(200, $status, $refused);
			self::assertSame($body, $refused);

			// A colleague with no picture: what the project's class draws.
			[$status, $body] = self::get(self::picturePath('bare'), self::$users['colleague']['session']);

			self::assertSame(200, $status, $body);
			self::assertSame([64, 64], ImageBytes::size($body));
			self::assertSame([0, 128, 0], \array_slice(ImageBytes::rgbAt($body, 32, 32), 0, 3));
		} finally {
			self::configure([
				'OZ_USER_PICTURE_ACCESS'   => SelfOrAdminPictureAccess::class,
				'OZ_USER_PICTURE_TOKENS'   => [],
				'OZ_USER_PICTURE_FALLBACK' => null,
			]);
		}
	}

	/**
	 * Writes `oz.users` keys and starts the server again: the dev server is one long-lived process,
	 * and OPcache holds the settings files it has already compiled.
	 *
	 * @param array<string, mixed> $settings
	 */
	private static function configure(array $settings): void
	{
		foreach ($settings as $key => $value) {
			self::proj()->setSetting('oz.users', $key, $value);
		}

		self::stopServer();
		self::$server = self::proj()->startServer('api', self::HOST);
	}

	private static function stopServer(): void
	{
		if (null !== self::$server && self::$server[0]->isRunning()) {
			self::$server[0]->stop(3);
		}

		self::$server = null;
	}

	private static function picturePath(string $user, string $tokens = ''): string
	{
		return '/users/' . self::$users[$user]['id'] . '/picture' . ('' === $tokens ? '' : '/' . $tokens);
	}

	/**
	 * Adds a user and signs them in.
	 *
	 * @return array{id: string, email: string, session: list<string>}
	 */
	private static function signUpAndIn(string $name, string $email): array
	{
		self::proj()->oz(
			'users',
			'add',
			'--user_civility=Mx',
			'--user_display_name=' . \ucfirst($name),
			'--user_first_name=' . \ucfirst($name),
			'--user_last_name=Test',
			'--user_email=' . $email,
			'--user_gender=None',
			'--user_birth_date=1990-05-17',
			'--user_pass=Picture_Pass_42',
			'--user_cc2=BJ',
		)->mustRun();

		[$status, $body, $headers] = self::post('/login', [
			'auth_user_type'             => 'user',
			'auth_user_identifier_type'  => 'email',
			'auth_user_identifier_value' => $email,
			'auth_user_password'         => 'Picture_Pass_42',
		]);

		self::assertSame(200, $status, $body);

		$cookies = [];

		foreach ($headers as $header) {
			if (\preg_match('~^Set-Cookie:\s*([^=;]+)=([^;]*)~i', $header, $m)) {
				$cookies[\urldecode($m[1])] = \urldecode($m[2]);
			}
		}

		$id = self::pdo()->prepare(\sprintf('SELECT user_id FROM "%s" WHERE user_email = ?', self::table('oz_users')));
		$id->execute([$email]);

		return [
			'id'      => (string) $id->fetchColumn(),
			'email'   => $email,
			'session' => [
				'Cookie: OZONE_SID=' . ($cookies['OZONE_SID'] ?? ''),
				'X-XSRF-TOKEN: ' . ($cookies['XSRF-TOKEN'] ?? ''),
			],
		];
	}

	/**
	 * A GET to the server.
	 *
	 * @param list<string> $headers
	 *
	 * @return array{0: int, 1: string, 2: array<string, string>} status, body, headers by lower-cased name
	 */
	private static function get(string $path, array $headers = []): array
	{
		[$status, $body, $lines] = self::send('GET', $path, null, $headers);
		$named                   = [];

		foreach ($lines as $line) {
			if (\str_contains($line, ':')) {
				[$name, $value]               = \explode(':', $line, 2);
				$named[\strtolower($name)]    = \trim($value);
			}
		}

		return [$status, $body, $named];
	}

	/**
	 * @param array<string, string> $fields
	 *
	 * @return array{0: int, 1: string, 2: list<string>}
	 */
	private static function post(string $path, array $fields): array
	{
		return self::send('POST', $path, \http_build_query($fields), [
			'Content-Type: application/x-www-form-urlencoded',
		]);
	}

	/**
	 * @param list<array{name: string, filename: string, type: string, content: string}> $files
	 * @param list<string>                                                               $headers
	 *
	 * @return array{0: int, 1: string, 2: list<string>}
	 */
	private static function multipart(string $path, array $files, array $headers): array
	{
		$boundary = '----OZoneTestBoundary' . \bin2hex(\random_bytes(8));
		$body     = '';

		foreach ($files as $file) {
			$body .= '--' . $boundary . "\r\n"
				. \sprintf('Content-Disposition: form-data; name="%s"; filename="%s"', $file['name'], $file['filename'])
				. "\r\nContent-Type: " . $file['type'] . "\r\n\r\n"
				. $file['content'] . "\r\n";
		}

		$body .= '--' . $boundary . "--\r\n";

		return self::send('POST', $path, $body, [
			...$headers,
			'Content-Type: multipart/form-data; boundary=' . $boundary,
		]);
	}

	/**
	 * @param list<string> $headers
	 *
	 * @return array{0: int, 1: string, 2: list<string>}
	 */
	private static function send(string $method, string $path, ?string $content, array $headers): array
	{
		self::assertNotNull(self::$server, 'The server is not running.');

		[, $host, $port] = self::$server;

		$opts = [
			'method'          => $method,
			'timeout'         => 20,
			'ignore_errors'   => true,
			'follow_location' => 0,
			'header'          => "Accept: application/json\r\n"
				. \implode('', \array_map(static fn (string $h): string => $h . "\r\n", $headers)),
		];

		if (null !== $content) {
			$opts['content'] = $content;
		}

		$body  = @\file_get_contents("http://{$host}:{$port}{$path}", false, \stream_context_create(['http' => $opts]));
		$lines = \http_get_last_response_headers() ?? [];

		$status = isset($lines[0]) && \preg_match('~HTTP/\d+(?:\.\d+)? (\d+)~', $lines[0], $m) ? (int) $m[1] : 0;

		return [$status, false === $body ? '' : $body, $lines];
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
	 * A table's name: a generated project prefixes them at random.
	 */
	private static function table(string $name): string
	{
		$stmt = self::pdo()->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE ?");
		$stmt->execute(['%' . $name]);

		$table = $stmt->fetchColumn();

		self::assertIsString($table);

		return $table;
	}

	/**
	 * Adds the country a user needs (`user_cc2`): no command creates one.
	 */
	private static function seedCountry(): void
	{
		$now = (string) \time();

		self::pdo()->prepare(\sprintf(
			'INSERT OR IGNORE INTO "%s" (country_cc2, country_calling_code, country_name, country_name_real,'
				. ' country_data, country_is_valid, country_created_at, country_updated_at, country_deleted,'
				. ' country_deleted_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?, 0, NULL)',
			self::table('oz_countries')
		))->execute(['BJ', '+229', 'Benin', 'Benin', '{}', $now, $now]);
	}
}
