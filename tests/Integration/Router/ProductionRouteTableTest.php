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

namespace OZONE\Tests\Integration\Router;

use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * The route table as production serves it: `ENV_MODE=production`, outside the console (the PHP
 * built-in server), where `OZone::createRouter()` saves a table on the first request and routes the
 * next ones through it.
 *
 * Two stub providers log each time a router registers them, which shows what a request registered.
 *
 * @internal
 *
 * @coversNothing
 */
final class ProductionRouteTableTest extends TestCase
{
	private static OZTestProject $proj;

	private static ?Process $server = null;

	private static string $host = '127.0.0.1';

	private static int $port = 0;

	private static string $log = '';

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$proj      = OZTestProject::create('route-table', fresh: true);
		$ns        = $proj->getNamespace();
		self::$log = $proj->getPath() . '/route_providers.log';

		// No route here touches the database: SQLite only keeps the project off a MySQL default.
		$proj->writeEnv([
			'ENV_MODE'    => 'production',
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $proj->getPath() . '/route_table_test.sqlite',
		]);

		foreach (['RouteTableProviderA', 'RouteTableProviderB'] as $stub) {
			$proj->writeFileFromStub($stub, "app/{$stub}.php", ['namespace' => $ns, 'log_file' => self::$log]);
			$proj->setSetting('oz.routes.api', "{$ns}\\{$stub}", true);
		}

		try {
			[self::$server, , self::$port] = $proj->startServer('api', self::$host);
		} catch (RuntimeException $e) {
			$proj->destroy();
			self::fail($e->getMessage());
		}

		self::$proj = $proj;
	}

	public static function tearDownAfterClass(): void
	{
		if (null !== self::$server && self::$server->isRunning()) {
			self::$server->stop(3);
		}

		if (isset(self::$proj)) {
			self::$proj->destroy();
		}

		parent::tearDownAfterClass();
	}

	public function testTheFirstRequestRegistersEveryProviderAndSavesTheTable(): void
	{
		foreach (self::tables() as $file) {
			\unlink($file);
		}

		self::resetLog();

		[$status, $data] = self::get('/rt-a');

		self::assertSame(200, $status);
		self::assertSame('A', $data['data']['provider'] ?? null);
		self::assertCount(1, self::tables());
		self::assertSame(['A', 'B'], self::registrations());
	}

	public function testALaterRequestRegistersOnlyTheProviderOfItsRoute(): void
	{
		self::ensureTable();
		self::resetLog();

		[$status, $data] = self::get('/rt-b');

		self::assertSame(200, $status);
		self::assertSame('B', $data['data']['provider'] ?? null);
		self::assertSame(['B'], self::registrations());
	}

	public function testARequestMatchingNoRouteRegistersNoProvider(): void
	{
		self::ensureTable();
		self::resetLog();

		[$status] = self::get('/rt-nowhere');

		self::assertSame(404, $status);
		self::assertSame([], self::registrations());
	}

	public function testALookupByNameRegistersTheProviderOfTheNamedRoute(): void
	{
		self::ensureTable();
		self::resetLog();

		[$status, $data] = self::get('/rt-b/link');

		self::assertSame(200, $status);
		self::assertStringEndsWith('/rt-a', (string) ($data['data']['link'] ?? ''));
		self::assertSame(['B', 'A'], self::registrations());
	}

	public function testAStatefulSettingChangeCompilesANewTable(): void
	{
		self::ensureTable();

		$before = self::tables();

		self::$proj->oz('settings', 'set', '--group=oz.gc', '--key=OZ_GC_PROBABILITY', '--value=0')->mustRun();
		self::resetLog();

		[$status, $data] = self::get('/rt-a');

		self::assertSame(200, $status);
		self::assertSame('A', $data['data']['provider'] ?? null);
		self::assertCount(\count($before) + 1, self::tables());
		self::assertSame(['A', 'B'], self::registrations());
	}

	public function testAScopeSettingAppliesToTheRequestAndCompilesANewTable(): void
	{
		self::ensureTable();

		$before = self::tables();

		self::$proj->oz('settings', 'set', '--scope=api', '--group=oz.gc', '--key=OZ_GC_PROBABILITY', '--value=7')
			->mustRun();

		[$status, $data] = self::get('/rt-b/setting');

		self::assertSame(200, $status);
		self::assertSame('7', (string) ($data['data']['value'] ?? ''));
		self::assertCount(\count($before) + 1, self::tables());
	}

	/**
	 * Makes a GET request to the test server.
	 *
	 * @return array{0: int, 1: array} the status code and the decoded JSON body
	 */
	private static function get(string $path): array
	{
		$ctx = \stream_context_create(['http' => [
			'timeout'       => 10,
			'ignore_errors' => true,
			'header'        => "Accept: application/json\r\n",
		]]);

		$body = \file_get_contents('http://' . self::$host . ':' . self::$port . $path, false, $ctx);

		self::assertIsString($body, 'No response for ' . $path);

		$status = 0;

		if (\preg_match('~^HTTP/\S+ (\d+)~', $http_response_header[0] ?? '', $m)) {
			$status = (int) $m[1];
		}

		$data = \json_decode($body, true);

		return [$status, \is_array($data) ? $data : []];
	}

	/**
	 * The route tables saved for the API router, in the api scope's cache directory.
	 *
	 * @return string[]
	 */
	private static function tables(): array
	{
		return \glob(self::$proj->getPath() . '/.ozone/cache/scopes/api/routes/api.*.php') ?: [];
	}

	/**
	 * Makes sure a table is saved, so the next request routes through it.
	 */
	private static function ensureTable(): void
	{
		if ([] === self::tables()) {
			self::get('/rt-a');
		}

		self::assertNotEmpty(self::tables());
	}

	/**
	 * The stub providers registered since the last {@see resetLog()}, in order.
	 *
	 * @return string[]
	 */
	private static function registrations(): array
	{
		$lines = \file(self::$log, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);

		return false === $lines ? [] : $lines;
	}

	private static function resetLog(): void
	{
		\file_put_contents(self::$log, '');
	}
}
