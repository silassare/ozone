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

use Override;
use OZONE\Core\Testing\OZTestProject;
use OZONE\Core\Web\DefaultRobotsTxtProvider;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * `/robots.txt` as a crawler gets it from a real server: answered by OZone's route in an API and a
 * web scope, by the default or by the provider a project names, and never by a file left in a
 * scope's document root.
 *
 * @internal
 */
#[CoversNothing]
final class RobotsTxtServedTest extends TestCase
{
	private const string HOST = '127.0.0.1';

	/**
	 * The file `oz scopes add` copied into every scope's document root before `/robots.txt` was a
	 * route, as it was.
	 */
	private const string OLD_FILE = "# \n"
		. "# This file contains rules to prevent the crawling and indexing of certain parts \n"
		. "# of your website by spiders of a major search engines likes Google and Yahoo.\n"
		. "# By managing these rules you can allow or disallow access to specific folders\n"
		. "# and files for such spiders.\n"
		. "# The good way to hide private data or save a lot of bandwidth. \n"
		. "#\n"
		. "#\n"
		. "# For more information about the robots.txt standard, see:\n"
		. "# http://www.robotstxt.org\n"
		. "#\n"
		. "# For syntax checking, see:\n"
		. "# http://www.sxw.org.uk/computing/robots/check.html\n"
		. "\n"
		. "\n"
		. "User-agent: *\n"
		. "Disallow: /\n";

	/**
	 * The condition every `.htaccess` written before carried, keeping `/robots.txt` from the route.
	 */
	private const string OLD_EXEMPTION = "\tRewriteCond %{REQUEST_URI}  !^/robots\\.txt\n";

	private static ?OZTestProject $proj = null;

	/**
	 * The server of each scope, by scope name.
	 *
	 * @var array<string, array{0: Process, 1: string, 2: int}>
	 */
	private static array $servers = [];

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$proj = OZTestProject::create('robots-txt', fresh: true);

		$proj->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $proj->getPath() . '/robots_txt.sqlite',
		]);
		$proj->oz('scopes', 'add', '--name=www', '--origin=http://localhost', '--api=false')->mustRun();
		$proj->writeFileFromStub('RobotsTestProvider', 'app/RobotsTestProvider.php', [
			'namespace' => $proj->getNamespace(),
		]);

		self::$proj = $proj;

		try {
			foreach (['api', 'www'] as $scope) {
				self::serve($scope);
			}
		} catch (RuntimeException $e) {
			self::stopServers();
			$proj->destroy();
			self::$proj = null;
			self::fail($e->getMessage());
		}
	}

	#[Override]
	public static function tearDownAfterClass(): void
	{
		self::stopServers();
		self::$proj?->destroy();
		self::$proj = null;

		parent::tearDownAfterClass();
	}

	public function testAScopeInstallWritesNoRobotsFileAndRoutesThePath(): void
	{
		foreach (['api', 'www'] as $scope) {
			self::assertFileDoesNotExist(self::path("public/{$scope}/robots.txt"));
			self::assertStringNotContainsString(
				'robots\.txt',
				\str_replace('RewriteRule ^robots\.txt$', '', self::read("public/{$scope}/.htaccess"))
			);
		}

		// Both serve the files of their document root: robots.txt is sent on before that.
		foreach (['api', 'www'] as $scope) {
			self::assertMatchesRegularExpression(
				'~RewriteRule \^robots\\\\\.txt\$\s+index\.php \[L\](?:\s+#[^\n]*)*'
					. '\s+RewriteCond %\{REQUEST_FILENAME\} !-d~',
				self::read("public/{$scope}/.htaccess"),
				$scope
			);
		}
	}

	public function testTheRouteAnswersTheDefaultInEveryScope(): void
	{
		foreach (['api', 'www'] as $scope) {
			$answer = self::get($scope, '/robots.txt');

			self::assertSame(200, $answer['status'], $scope . ': ' . $answer['body']);
			self::assertSame('text/plain; charset=utf-8', $answer['headers']['content-type'] ?? null);
			self::assertSame(DefaultRobotsTxtProvider::CONTENT, $answer['body']);
		}
	}

	public function testAFileLeftInTheDocumentRootDoesNotShadowTheRoute(): void
	{
		$proj = self::proj();

		$proj->writeFile('public/www/robots.txt', "User-agent: *\nAllow: /\n");
		$proj->writeFile('public/www/humans.txt', "Made by people.\n");

		try {
			self::assertSame(DefaultRobotsTxtProvider::CONTENT, self::get('www', '/robots.txt')['body']);
			// Any other file of the document root is still the web server's to serve.
			self::assertSame("Made by people.\n", self::get('www', '/humans.txt')['body']);
		} finally {
			\unlink(self::path('public/www/robots.txt'));
			\unlink(self::path('public/www/humans.txt'));
		}
	}

	public function testTheProviderAProjectNamesAnswersPerScopeAndPerHost(): void
	{
		$proj     = self::proj();
		$provider = $proj->getNamespace() . '\RobotsTestProvider';
		$www_file = 'scopes/www/settings/oz.request.php';
		$www_own  = self::read($www_file);

		$proj->setSetting('oz.request', 'OZ_ROBOTS_TXT_PROVIDER', $provider);
		self::serveAgain();

		try {
			$api      = self::get('api', '/robots.txt');
			$www      = self::get('www', '/robots.txt', ['Host: www.example.test']);
			$www_shop = self::get('www', '/robots.txt', ['Host: shop.example.test']);

			self::assertSame(200, $api['status'], $api['body']);
			self::assertSame('text/plain; charset=utf-8', $api['headers']['content-type'] ?? null);
			self::assertSame("# api on 127.0.0.1\nUser-agent: *\nAllow: /\n", $api['body']);
			self::assertSame("# www on www.example.test\nUser-agent: *\nAllow: /\n", $www['body']);
			self::assertSame("# www on shop.example.test\nUser-agent: *\nAllow: /\n", $www_shop['body']);

			// A scope names a provider of its own in its settings: here, OZone's default back.
			$proj->writeFile($www_file, "<?php\n\nreturn [\n\t'OZ_DEFAULT_ORIGIN' => 'http://localhost',\n"
				. "\t'OZ_ROBOTS_TXT_PROVIDER' => " . DefaultRobotsTxtProvider::class . "::class,\n];\n");
			self::serveAgain();

			self::assertSame(DefaultRobotsTxtProvider::CONTENT, self::get('www', '/robots.txt')['body']);
			self::assertSame("# api on 127.0.0.1\nUser-agent: *\nAllow: /\n", self::get('api', '/robots.txt')['body']);
		} finally {
			$proj->writeFile($www_file, $www_own);
			$proj->setSetting('oz.request', 'OZ_ROBOTS_TXT_PROVIDER', DefaultRobotsTxtProvider::class);
			self::serveAgain();
		}
	}

	public function testDoctorWarnsOfWhatAnOlderVersionLeftInTheDocumentRoot(): void
	{
		$proj = self::proj();

		self::assertSame([['robots.txt', 'ok', 'answered by its route']], self::doctorRows());

		$api_htaccess = self::read('public/api/.htaccess');

		$proj->writeFile('public/api/robots.txt', self::OLD_FILE);
		$proj->writeFile('public/api/.htaccess', \str_replace(
			"\tRewriteCond %{REQUEST_URI}      !^/favicon\\.ico\n",
			self::OLD_EXEMPTION . "\tRewriteCond %{REQUEST_URI}      !^/favicon\\.ico\n",
			$api_htaccess
		));
		$proj->writeFile('public/www/robots.txt', "User-agent: *\nAllow: /\n");

		try {
			$rows = self::doctorRows();

			self::assertCount(2, $rows);
			self::assertSame(['robots.txt: api', 'warn'], [$rows[0][0], $rows[0][1]]);
			self::assertStringContainsString('robots.txt has the rules the route answers by default', $rows[0][2]);
			self::assertStringContainsString('.htaccess answers /robots.txt from a file', $rows[0][2]);
			self::assertSame(['robots.txt: www', 'warn'], [$rows[1][0], $rows[1][1]]);
			self::assertStringContainsString('robots.txt has rules of its own', $rows[1][2]);
			self::assertStringNotContainsString('.htaccess', $rows[1][2]);

			// Nothing was removed: a server still answering from the file would answer 404 without it.
			self::assertFileExists(self::path('public/api/robots.txt'));
			self::assertFileExists(self::path('public/www/robots.txt'));
		} finally {
			$proj->writeFile('public/api/.htaccess', $api_htaccess);
			\unlink(self::path('public/api/robots.txt'));
			\unlink(self::path('public/www/robots.txt'));
		}
	}

	/**
	 * The robots.txt rows of `oz doctor check`, as `[name, status, detail]`.
	 *
	 * @return list<array{0: string, 1: string, 2: string}>
	 */
	private static function doctorRows(): array
	{
		// The other checks of a development project may fail: only the robots.txt rows are read.
		$doctor = self::proj()->oz('doctor', 'check', '--json');

		$doctor->run();

		$answer = \json_decode($doctor->getOutput(), true);
		$rows   = [];

		self::assertIsArray($answer, $doctor->getOutput() . $doctor->getErrorOutput());

		foreach ((array) ($answer['data']['checks'] ?? []) as $row) {
			if (\is_array($row) && \str_starts_with((string) ($row['name'] ?? ''), 'robots.txt')) {
				$rows[] = [(string) $row['name'], (string) ($row['status'] ?? ''), (string) ($row['detail'] ?? '')];
			}
		}

		return $rows;
	}

	/**
	 * The project this class runs against, which its own setup created.
	 */
	private static function proj(): OZTestProject
	{
		$project = self::$proj;

		self::assertNotNull($project, 'The test project was not created.');

		return $project;
	}

	private static function path(string $relative): string
	{
		return self::proj()->getPath() . '/' . $relative;
	}

	private static function read(string $relative): string
	{
		return (string) \file_get_contents(self::path($relative));
	}

	/**
	 * Starts the server of a scope.
	 */
	private static function serve(string $scope): void
	{
		self::$servers[$scope] = self::proj()->startServer($scope, self::HOST);
	}

	/**
	 * Starts the servers again, for a settings change to reach them: the dev server is one long-lived
	 * process, and OPcache holds the settings files it has already compiled.
	 */
	private static function serveAgain(): void
	{
		self::stopServers();

		foreach (['api', 'www'] as $scope) {
			self::serve($scope);
		}
	}

	private static function stopServers(): void
	{
		foreach (self::$servers as [$server]) {
			$server->stop();
		}

		self::$servers = [];
	}

	/**
	 * Makes a GET request to a scope's server, following no redirection.
	 *
	 * @param list<string> $headers request headers, as lines
	 *
	 * @return array{status: int, headers: array<string, string>, body: string}
	 */
	private static function get(string $scope, string $path, array $headers = []): array
	{
		[, $host, $port] = self::$servers[$scope];

		$ctx = \stream_context_create(['http' => [
			'timeout'         => 10,
			'ignore_errors'   => true,
			'follow_location' => 0,
			'header'          => \implode('', \array_map(static fn (string $h): string => $h . "\r\n", $headers)),
		]]);

		$url  = "http://{$host}:{$port}{$path}";
		$body = \file_get_contents($url, false, $ctx);

		self::assertIsString($body, 'No response for ' . $url);

		$status = 0;
		$found  = [];

		foreach (\http_get_last_response_headers() ?? [] as $line) {
			if (\preg_match('~^HTTP/\S+ (\d+)~', $line, $m)) {
				$status = (int) $m[1];
			} elseif (\str_contains($line, ':')) {
				[$name, $value] = \explode(':', $line, 2);

				$found[\strtolower(\trim($name))] = \trim($value);
			}
		}

		return ['status' => $status, 'headers' => $found, 'body' => $body];
	}
}
