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
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * The direct link of a public file, fetched as a client would: built in an API request on
 * `OZ_PUBLIC_FILES_BASE_URL`, and answered by a server whose document root is `public/`, which links
 * the application's public files directory as `static`.
 *
 * @internal
 */
#[CoversNothing]
final class PublicFileLinkServedTest extends TestCase
{
	private const string HOST = '127.0.0.1';

	private static ?OZTestProject $proj = null;

	/**
	 * The servers, by scope name: `root` serves `public/`, `api` the API scope.
	 *
	 * @var array<string, array{0: Process, 1: string, 2: int}>
	 */
	private static array $servers = [];

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$proj = OZTestProject::create('public-file-link', fresh: true);
		$ns   = $proj->getNamespace();

		$proj->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $proj->getPath() . '/public_file_link.sqlite',
		]);
		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->writeFileFromStub('PublicFileLinkRoutesProvider', 'app/PublicFileLinkRoutesProvider.php', [
			'namespace' => $ns,
		]);
		$proj->setSetting('oz.routes', "{$ns}\\PublicFileLinkRoutesProvider", true);
		$proj->setSetting('oz.files', 'OZ_PUBLIC_URI_DIRECT_ACCESS_ENABLED', true);

		self::$proj = $proj;

		try {
			// The server of public/ first: the base URL names its port, and the API server must start
			// with the setting already written, since it keeps the settings it compiled.
			self::$servers['root'] = $proj->startServer('root', self::HOST);

			$proj->setSetting('oz.files', 'OZ_PUBLIC_FILES_BASE_URL', self::rootBase());

			self::$servers['api'] = $proj->startServer('api', self::HOST);
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

	public function testTheLinkBuiltOnTheBaseUrlIsServed(): void
	{
		$answer = self::get('api', '/pf-link');

		self::assertSame(200, $answer['status'], $answer['body']);

		$data = \json_decode($answer['body'], true)['data'] ?? null;

		self::assertIsArray($data, $answer['body']);

		$link = (string) $data['uri'];
		$file = self::fetch($link);

		self::assertSame(200, $file['status'], $link . ': ' . $file['body']);
		self::assertSame($data['content'], $file['body']);
		self::assertSame(self::rootBase() . 'uploads/' . $data['ref'], $link);
	}

	public function testDoctorShowsTheBaseAndSuggestsTheRootVhostWhenItIsMissing(): void
	{
		$proj = self::proj();

		self::assertSame([['Public files', 'ok', 'linked at ' . self::rootBase(), '']], self::doctorRows());

		$proj->setSetting('oz.files', 'OZ_PUBLIC_FILES_BASE_URL', null);
		$proj->setSetting('oz.request', 'OZ_DEFAULT_ORIGIN', 'https://www.example.test');

		try {
			$rows = self::doctorRows();

			self::assertCount(1, $rows);
			self::assertSame(['Public files', 'fail'], [$rows[0][0], $rows[0][1]]);
			self::assertStringNotContainsString('https://', $rows[0][3]);

			$proj->oz('deploy', 'init', '--target=bare')->mustRun();

			$rows = self::doctorRows();

			self::assertSame(['Public files', 'fail'], [$rows[0][0], $rows[0][1]]);
			self::assertStringEndsWith('serves it: https://www.example.test/static/', $rows[0][3]);
		} finally {
			$proj->setSetting('oz.files', 'OZ_PUBLIC_FILES_BASE_URL', self::rootBase());
		}
	}

	/**
	 * The base URL of the public files, on the server of `public/`.
	 */
	private static function rootBase(): string
	{
		return \sprintf('http://%s:%d/static/', self::HOST, self::$servers['root'][2]);
	}

	/**
	 * The `Public files` rows of `oz doctor check`, as `[name, status, detail, fix]`.
	 *
	 * @return list<array{0: string, 1: string, 2: string, 3: string}>
	 */
	private static function doctorRows(): array
	{
		// The other checks of a development project may fail: only the public files rows are read.
		$doctor = self::proj()->oz('doctor', 'check', '--json');

		$doctor->run();

		$answer = \json_decode($doctor->getOutput(), true);
		$rows   = [];

		self::assertIsArray($answer, $doctor->getOutput() . $doctor->getErrorOutput());

		foreach ((array) ($answer['data']['checks'] ?? []) as $row) {
			if (\is_array($row) && 'Public files' === ($row['name'] ?? null)) {
				$rows[] = [
					(string) $row['name'],
					(string) ($row['status'] ?? ''),
					(string) ($row['detail'] ?? ''),
					(string) ($row['fix'] ?? ''),
				];
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

	private static function stopServers(): void
	{
		foreach (self::$servers as [$server]) {
			$server->stop();
		}

		self::$servers = [];
	}

	/**
	 * Makes a GET request to a scope's server.
	 *
	 * @return array{status: int, body: string}
	 */
	private static function get(string $scope, string $path): array
	{
		[, $host, $port] = self::$servers[$scope];

		return self::fetch("http://{$host}:{$port}{$path}");
	}

	/**
	 * Makes a GET request, following no redirection.
	 *
	 * @return array{status: int, body: string}
	 */
	private static function fetch(string $url): array
	{
		$ctx = \stream_context_create(['http' => [
			'timeout'         => 10,
			'ignore_errors'   => true,
			'follow_location' => 0,
		]]);

		$body = \file_get_contents($url, false, $ctx);

		self::assertIsString($body, 'No response for ' . $url);

		$status = 0;

		foreach (\http_get_last_response_headers() ?? [] as $line) {
			if (\preg_match('~^HTTP/\S+ (\d+)~', $line, $m)) {
				$status = (int) $m[1];
			}
		}

		return ['status' => $status, 'body' => $body];
	}
}
