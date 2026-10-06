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
 * still loading, and the CRUD listeners attach once per process. Attaching then, a listener reaching
 * its table through the generated classes was skipped when the class loading was its entity
 * (`class_exists()` is false for a class still loading), leaving the table without its access
 * rules for the worker's life, or stopped the request with a compile error when it was another
 * class the entity names (the query class). They now attach once that class is complete: whichever
 * class comes first, the rules hold, attached by the table's name or through its generated classes.
 *
 * @internal
 *
 * @coversNothing
 */
final class FirstClassLoadedTest extends TestCase
{
	/** The listener refusing an anonymous read, by how it attaches: its stub. */
	private const LISTENERS = [
		'by-table'  => 'FirstClassCrudHandler',
		'generated' => 'FirstClassGeneratedCrudListener',
	];

	/** @var array<string, OZTestProject> */
	private static array $projects = [];

	public static function tearDownAfterClass(): void
	{
		foreach (self::$projects as $proj) {
			$proj->destroy();
		}

		self::$projects = [];

		parent::tearDownAfterClass();
	}

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function provideKindCases(): iterable
	{
		foreach (\array_keys(self::LISTENERS) as $listener) {
			foreach (['entity', 'query', 'controller', 'results', 'crud'] as $kind) {
				yield "{$listener}, {$kind} first" => [$listener, $kind];
			}
		}
	}

	/**
	 * A server of its own: its one worker initializes the database on this request.
	 *
	 * @dataProvider provideKindCases
	 */
	public function testTheTableRulesHoldWhicheverClassLoadsFirst(string $listener, string $kind): void
	{
		[$server, $host, $port] = self::project($listener)->startServer('api');

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

	/**
	 * The project whose files table the given listener guards, built once.
	 */
	private static function project(string $listener): OZTestProject
	{
		if (isset(self::$projects[$listener])) {
			return self::$projects[$listener];
		}

		$stub = self::LISTENERS[$listener];
		$proj = self::$projects[$listener] = OZTestProject::create('first-class-' . $listener, fresh: true);
		$ns   = $proj->getNamespace();

		$proj->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $proj->getPath() . '/first_class.sqlite',
		]);
		$proj->writeFileFromStub('FirstClassRoutesProvider', 'app/FirstClassRoutesProvider.php', [
			'namespace' => $ns,
		]);
		$proj->writeFileFromStub($stub, "app/{$stub}.php", ['namespace' => $ns]);
		$proj->setSetting('oz.routes.api', "{$ns}\\FirstClassRoutesProvider", true);
		$proj->setSetting('oz.gobl.crud', "{$ns}\\{$stub}", true);
		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		return $proj;
	}
}
