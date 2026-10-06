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

use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\TestCase;

/**
 * The database is initialized when a request first loads a generated ORM class, while that class is
 * still loading, and the CRUD listeners attach then, once per process. A listener that reached its
 * table through the generated classes was skipped when the class loading was its entity
 * (`class_exists()` is false for a class still loading), leaving the table without its access
 * rules for the worker's life, or stopped the request with a compile error when it was another
 * class the entity names (the query class). Whichever class comes first, the rules hold.
 *
 * @internal
 *
 * @coversNothing
 */
final class FirstClassLoadedTest extends TestCase
{
	private static ?OZTestProject $proj = null;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$proj = self::$proj = OZTestProject::create('first-class-loaded', fresh: true);
		$ns   = $proj->getNamespace();

		$proj->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $proj->getPath() . '/first_class.sqlite',
		]);
		$proj->writeFileFromStub('FirstClassRoutesProvider', 'app/FirstClassRoutesProvider.php', [
			'namespace' => $ns,
		]);
		$proj->writeFileFromStub('FirstClassCrudHandler', 'app/FirstClassCrudHandler.php', [
			'namespace' => $ns,
		]);
		$proj->setSetting('oz.routes.api', "{$ns}\\FirstClassRoutesProvider", true);
		$proj->setSetting('oz.gobl.crud', "{$ns}\\FirstClassCrudHandler", true);
		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();
	}

	public static function tearDownAfterClass(): void
	{
		self::$proj?->destroy();
		self::$proj = null;

		parent::tearDownAfterClass();
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function provideKindCases(): iterable
	{
		foreach (['entity', 'query', 'controller', 'results', 'crud'] as $kind) {
			yield $kind => [$kind];
		}
	}

	/**
	 * A server of its own: its one worker initializes the database on this request.
	 *
	 * @dataProvider provideKindCases
	 */
	public function testTheTableRulesHoldWhicheverClassLoadsFirst(string $kind): void
	{
		$proj = self::$proj;

		self::assertNotNull($proj);

		[$server, $host, $port] = $proj->startServer('api');

		try {
			$body   = @\file_get_contents(
				"http://{$host}:{$port}/first-class/{$kind}",
				false,
				\stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 30]])
			);
			$status = (int) \explode(' ', $http_response_header[0] ?? 'HTTP/1.1 0')[1];
		} finally {
			$server->stop();
		}

		// Refused by the rules, not answered (200) nor broken (500).
		self::assertSame(403, $status, (string) $body);
	}
}
