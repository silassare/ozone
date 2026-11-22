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

namespace OZONE\Tests\Integration\Db;

use Override;
use OZONE\Core\Testing\OZTestProject;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * `oz db seed` in a real project: reference data written once and never again over an edit, and
 * demo data only when asked for. Tests run in declaration order on one project.
 *
 * @internal
 */
#[CoversNothing]
final class DbSeedTest extends TestCase
{
	private static ?OZTestProject $proj = null;

	private static string $sqlite = '';

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$proj         = OZTestProject::create('db-seed', fresh: true);
		$ns           = $proj->getNamespace();
		self::$sqlite = $proj->getPath() . '/db_seed_test.sqlite';

		$proj->writeEnv(['OZ_DB_RDBMS' => 'sqlite', 'OZ_DB_HOST' => self::$sqlite]);
		$proj->setSetting('oz.db.schema', 'seed_terms', [
			'plural_name'   => 'seed_terms',
			'singular_name' => 'seed_term',
			'column_prefix' => 'term',
			'columns'       => [
				'id'    => ['type' => 'bigint', 'auto_increment' => true, 'unsigned' => true],
				'code'  => ['type' => 'string', 'max' => 32],
				'label' => ['type' => 'string', 'max' => 60],
			],
			'constraints'   => [['type' => 'primary_key', 'columns' => ['id']]],
		]);

		$proj->writeFile('app/TermsSeeder.php', self::seeder($ns, 'TermsSeeder', false, [
			['term_code' => 'a', 'term_label' => 'Alpha'],
			['term_code' => 'b', 'term_label' => 'Beta'],
		]));
		$proj->writeFile('app/DemoTermsSeeder.php', self::seeder($ns, 'DemoTermsSeeder', true, [
			['term_code' => 'demo', 'term_label' => 'Invented'],
		]));
		$proj->setSetting('oz.seeders', "{$ns}\\TermsSeeder", true);
		$proj->setSetting('oz.seeders', "{$ns}\\DemoTermsSeeder", true);

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();
		$proj->oz('db', 'build', '--class-only')->mustRun();

		self::$proj = $proj;
	}

	#[Override]
	public static function tearDownAfterClass(): void
	{
		self::$proj?->destroy();
		self::$proj = null;

		parent::tearDownAfterClass();
	}

	public function testReferenceDataIsWrittenAndDemoDataIsNot(): void
	{
		self::assertSame(['TermsSeeder' => 2], self::seed());
		self::assertSame(['a' => 'Alpha', 'b' => 'Beta'], self::terms());
	}

	public function testASecondRunWritesNothing(): void
	{
		self::assertSame(['TermsSeeder' => 0], self::seed());
	}

	public function testAnEditIsNeverOverwritten(): void
	{
		self::pdo()->exec(\sprintf(
			"UPDATE %s SET term_label = 'Edited' WHERE term_code = 'a'",
			self::table()
		));

		self::assertSame(['TermsSeeder' => 0], self::seed());
		self::assertSame(['a' => 'Edited', 'b' => 'Beta'], self::terms());
	}

	public function testDemoDataIsWrittenOnlyWhenAskedFor(): void
	{
		self::assertSame(['TermsSeeder' => 0, 'DemoTermsSeeder' => 1], self::seed('--demo'));
		self::assertSame('Invented', self::terms()['demo'] ?? null);
	}

	/**
	 * What `oz db seed` wrote, by seeder's short name.
	 *
	 * @return array<string, int>
	 */
	private static function seed(string ...$args): array
	{
		$proc = self::project()->oz('db', 'seed', ...$args, ...['--json']);
		$proc->mustRun();

		$out = \json_decode($proc->getOutput(), true);

		self::assertIsArray($out);

		$written = [];

		foreach ($out['data']['seeders'] ?? [] as $one) {
			$written[\substr((string) \strrchr('\\' . $one['seeder'], '\\'), 1)] = $one['written'];
		}

		return $written;
	}

	/**
	 * The terms in the table, label by code.
	 *
	 * @return array<string, string>
	 */
	private static function terms(): array
	{
		$rows = self::pdo()->query(\sprintf(
			'SELECT term_code, term_label FROM %s ORDER BY term_code',
			self::table()
		));

		self::assertNotFalse($rows);

		/** @var array<string, string> $terms */
		$terms = $rows->fetchAll(PDO::FETCH_KEY_PAIR);

		return $terms;
	}

	/**
	 * The table's name in the database: the project's table prefix and its name, as Gobl joins them.
	 */
	private static function table(): string
	{
		$prefix = self::project()->tablePrefix();

		return null === $prefix || '' === $prefix ? 'seed_terms' : $prefix . '_seed_terms';
	}

	private static function pdo(): PDO
	{
		return new PDO('sqlite:' . self::$sqlite, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
	}

	private static function project(): OZTestProject
	{
		$project = self::$proj;

		self::assertNotNull($project, 'The test project was not created.');

		return $project;
	}

	/**
	 * A seeder class of the project, inserting the given rows into `seed_terms` by code.
	 *
	 * @param list<array<string, string>> $rows
	 */
	private static function seeder(string $ns, string $name, bool $demo, array $rows): string
	{
		return \sprintf(
			<<<'PHP'
				<?php

				declare(strict_types=1);

				namespace %s;

				use OZONE\Core\Seeders\Interfaces\SeederInterface;
				use OZONE\Core\Seeders\Seeders;

				final class %s implements SeederInterface
				{
					public function run(): int
					{
						return Seeders::insertMissing(db()->getTableOrFail('seed_terms'), ['term_code'], %s);
					}

					public static function isDemo(): bool
					{
						return %s;
					}
				}

				PHP,
			$ns,
			$name,
			\var_export($rows, true),
			$demo ? 'true' : 'false'
		);
	}
}
