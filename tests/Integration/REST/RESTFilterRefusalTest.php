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

namespace OZONE\Tests\Integration\REST;

use OZONE\Core\Testing\DbTestConfig;
use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * A REST client's filters, on a real server and every configured database: a refused filter says which
 * item and why in the answer, and a boolean column takes the words a query string carries. The project
 * also serves a second service over the same table.
 *
 * @internal
 *
 * @coversNothing
 */
final class RESTFilterRefusalTest extends TestCase
{
	/** @var array<string, OZTestProject> */
	private static array $projects = [];

	/** @var array<string, array{0: Process, 1: string, 2: int}> */
	private static array $servers = [];

	/** @var array<string, null|string> */
	private static array $dbFiles = [];

	public static function tearDownAfterClass(): void
	{
		foreach (self::$servers as [$server]) {
			$server->stop();
		}

		foreach (self::$projects as $proj) {
			$proj->destroy();
		}

		foreach (self::$dbFiles as $file) {
			if (null !== $file && \is_file($file)) {
				\unlink($file);
			}
		}

		self::$servers  = [];
		self::$projects = [];
		self::$dbFiles  = [];

		parent::tearDownAfterClass();
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testSetup(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('rest-filters-' . $rdbms, shared: true, fresh: true);
		$ns    = $proj->getNamespace();

		self::$projects[$rdbms] = $proj;
		self::$dbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;

		$config = $proj->useDatabase($config);
		$proj->setSetting('oz.db.schema', 'terms', [
			'plural_name'   => 'terms',
			'singular_name' => 'term',
			'column_prefix' => 'trm',
			'columns'       => [
				'id'        => ['type' => 'bigint', 'auto_increment' => true, 'unsigned' => true],
				'kind'      => ['type' => 'string', 'max' => 32],
				'is_active' => ['type' => 'bool', 'default' => false],
			],
			'constraints'   => [
				['type' => 'primary_key', 'columns' => ['id']],
			],
		]);
		$proj->writeFileFromStub('FilterTermsRESTService', 'app/FilterTermsRESTService.php', ['namespace' => $ns]);
		$proj->setSetting('oz.routes.api', "{$ns}\\FilterTermsRESTService", true);
		$proj->writeFileFromStub('FilterTermsArchiveRESTService', 'app/FilterTermsArchiveRESTService.php', [
			'namespace' => $ns,
		]);
		$proj->setSetting('oz.routes.api', "{$ns}\\FilterTermsArchiveRESTService", true);

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();
		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();

		self::$servers[$rdbms] = $proj->startServer('api');

		[$status] = self::get($config, '/terms-seed');

		self::assertSame(200, $status);
	}

	/**
	 * Two services over one table register the same access rights actions, named after the table: the
	 * second one used to stop the boot ("Action 'terms.read' already registered").
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testASecondServiceOverTheSameTableServesToo(DbTestConfig $config): void
	{
		[$status, $json] = self::get($config, '/terms-archive');

		self::assertSame(200, $status, \json_encode($json));
		self::assertCount(3, $json['data']['items'] ?? [], \json_encode($json));
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testTwoConditionsWithoutGlueAreRefusedSayingWhere(DbTestConfig $config): void
	{
		[$status, $json] = self::get(
			$config,
			'/terms?filters[0][0]=kind&filters[0][1]=eq&filters[0][2]=format'
				. '&filters[1][0]=kind&filters[1][1]=eq&filters[1][2]=format'
		);

		self::assertSame(400, $status, \json_encode($json));
		self::assertSame('Failed to apply filters to query.', $json['data']['type'] ?? null, \json_encode($json));
		self::assertSame(
			'item at [1]: expected "and" or "or" between two conditions, found "array".',
			$json['data']['data']['reason']
		);
		self::assertSame([1], $json['data']['data']['position']);
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testARefusedValueNamesTheColumnAndItsOperators(DbTestConfig $config): void
	{
		[$status, $json] = self::get($config, '/terms?filters[0]=is_active&filters[1]=eq&filters[2]=maybe');

		self::assertSame(400, $status, \json_encode($json));

		$data = $json['data']['data'];

		self::assertStringStartsWith('condition at [0] ("is_active" eq) refused: ', $data['reason']);
		self::assertSame('is_active', $data['column']);
		self::assertSame('eq', $data['operator']);
		self::assertSame(['eq', 'neq', 'is_false', 'is_true'], $data['allowed_operators']);
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testABooleanColumnTakesEqWithQueryStringWords(DbTestConfig $config): void
	{
		foreach (['1' => 2, 'true' => 2, '0' => 1, 'false' => 1] as $word => $count) {
			[$status, $json] = self::get($config, '/terms?filters[0]=is_active&filters[1]=eq&filters[2]=' . $word);

			self::assertSame(200, $status, (string) $word . ': ' . \json_encode($json));
			self::assertSame($count, $json['data']['total'] ?? null, (string) $word . ': ' . \json_encode($json));
		}
	}

	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('rest-filters');
	}

	/**
	 * @return array{0: int, 1: array<string, mixed>}
	 */
	private static function get(DbTestConfig $config, string $path): array
	{
		if (!isset(self::$servers[$config->rdbms])) {
			self::fail(\sprintf('Project for %s not initialized. Did testSetup pass?', $config->rdbms));
		}

		[, $host, $port] = self::$servers[$config->rdbms];

		$body   = @\file_get_contents(
			"http://{$host}:{$port}{$path}",
			false,
			\stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 30, 'header' => "Accept: application/json\r\n"]])
		);
		$status = (int) \explode(' ', $http_response_header[0] ?? 'HTTP/1.1 0')[1];

		return [$status, (array) \json_decode((string) $body, true)];
	}
}
