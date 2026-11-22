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

namespace OZONE\Tests\Integration\Revisions;

use Override;
use OZONE\Core\Testing\DbTestConfig;
use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Resources kept with revisions in a real project, on each database: what the grouped read of the
 * current revisions and the unique key a race is decided by mean to that engine. The project ships
 * a command of its own that writes, as the owner of a namespace would, and `oz revisions` reads the
 * history and restores.
 *
 * @internal
 */
#[CoversNothing]
final class RevisionsInProjectTest extends TestCase
{
	private const string NAMESPACE = 'test.pages';

	/** @var array<string, OZTestProject> */
	private static array $projects = [];

	/** @var array<string, null|string> */
	private static array $dbFiles = [];

	#[Override]
	public static function tearDownAfterClass(): void
	{
		foreach (self::$projects as $proj) {
			$proj->destroy();
		}

		foreach (self::$dbFiles as $file) {
			if (null !== $file && \is_file($file)) {
				\unlink($file);
			}
		}

		self::$projects = [];
		self::$dbFiles  = [];

		parent::tearDownAfterClass();
	}

	#[DataProvider('provideDbConfig')]
	public function testResourcesAreKeptWithTheirHistoryAndOneOfSeveralRacingWritersWins(DbTestConfig $config): void
	{
		$rdbms  = $config->rdbms;
		$proj   = OZTestProject::create('revisions-' . $rdbms, shared: true, fresh: true);
		$config = $proj->useDatabase($config);

		self::$projects[$rdbms] = $proj;
		self::$dbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;

		$ns = $proj->getNamespace();

		$proj->writeFile('app/RevisionsProbeCmd.php', self::probe($ns));
		$proj->setSetting('oz.cli', 'probe', "{$ns}\\RevisionsProbeCmd");

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		self::assertSame(['applied' => true, 'number' => 1], self::write($proj, 'home', ['v' => 1], null));
		self::assertSame(['applied' => true, 'number' => 2], self::write($proj, 'home', ['v' => 2], 1));
		self::assertSame(['applied' => true, 'number' => 1], self::write($proj, 'about', ['v' => 1], null));
		self::assertSame(['applied' => true, 'number' => 1], self::write($proj, 'gone', ['v' => 1], null));
		self::assertSame(['applied' => true, 'number' => 2], self::write($proj, 'gone', null, 1));

		// The latest of each key, and nothing for the one whose latest is a removal.
		self::assertSame(['about' => 1, 'home' => 2], self::current($proj));

		// What decides a race, asked directly: a second revision 2 of `home` is refused by the
		// database itself, since a race between processes is too narrow to hit on every engine.
		$duplicate = $proj->oz('probe', 'duplicate', '--key=home', '--number=2');
		$duplicate->mustRun();

		self::assertSame(['refused' => true], self::dataOf($duplicate));

		// Five writers, all based on revision 2, at once: the database keeps one of them.
		$racers = [];

		for ($i = 0; $i < 5; ++$i) {
			$racer = $proj->oz(
				'probe',
				'write',
				'--key=home',
				'--content=' . \json_encode(['racer' => $i]),
				'--based-on=2'
			);
			$racer->start();
			$racers[] = $racer;
		}

		$applied = 0;

		foreach ($racers as $racer) {
			$racer->wait();

			if (true === (self::dataOf($racer)['applied'] ?? null)) {
				++$applied;
			}
		}

		self::assertSame(1, $applied);

		$restore = $proj->oz(
			'revisions',
			'restore',
			'--namespace=' . self::NAMESPACE,
			'--key=home',
			'--number=1',
			'--json'
		);
		$restore->mustRun();

		self::assertTrue(self::dataOf($restore)['applied'] ?? null);

		$history = $proj->oz('revisions', 'history', '--namespace=' . self::NAMESPACE, '--key=home', '--json');
		$history->mustRun();

		self::assertSame(
			[4, 3, 2, 1],
			\array_column(self::dataOf($history)['revisions'] ?? [], 'number')
		);
		self::assertSame('Restored revision 1.', self::dataOf($history)['revisions'][0]['note'] ?? null);
		self::assertSame(['about' => 1, 'home' => 4], self::current($proj));
	}

	/**
	 * @return iterable<string, array{DbTestConfig}>
	 */
	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('revisions');
	}

	/**
	 * What the project's probe answered for a write.
	 *
	 * @param null|array<string, mixed> $content
	 *
	 * @return array<string, mixed>
	 */
	private static function write(OZTestProject $proj, string $key, ?array $content, ?int $based_on): array
	{
		$proc = $proj->oz(
			'probe',
			'write',
			'--key=' . $key,
			'--content=' . \json_encode($content),
			'--based-on=' . ($based_on ?? '')
		);
		$proc->mustRun();

		return self::dataOf($proc);
	}

	/**
	 * The number of the current revision of each key, as the namespace answers them all.
	 *
	 * @return array<string, int>
	 */
	private static function current(OZTestProject $proj): array
	{
		$proc = $proj->oz('probe', 'current');
		$proc->mustRun();

		/** @var array<string, int> $current */
		$current = self::dataOf($proc)['current'] ?? [];

		return $current;
	}

	/**
	 * The `data` of a command's JSON answer.
	 *
	 * @return array<string, mixed>
	 */
	private static function dataOf(Process $proc): array
	{
		$out = \json_decode($proc->getOutput(), true);

		self::assertIsArray($out, $proc->getOutput() . $proc->getErrorOutput());

		/** @var array<string, mixed> $data */
		$data = \is_array($out['data'] ?? null) ? $out['data'] : [];

		return $data;
	}

	/**
	 * A command of the project writing in the namespace, as the owner of one would.
	 */
	private static function probe(string $ns): string
	{
		return \sprintf(
			<<<'PHP'
				<?php

				declare(strict_types=1);

				namespace %s;

				use Gobl\DBAL\Exceptions\DBALUniqueViolationException;
				use Kli\KliArgs;
				use OZONE\Core\Cli\Command;
				use OZONE\Core\Db\OZRevision;
				use OZONE\Core\Revisions\Revisions;

				final class RevisionsProbeCmd extends Command
				{
					protected function describe(): void
					{
						$write = $this->action('write', 'Write a revision.');
						$write->option('key', 'k', [], 1)->required()->string();
						$write->option('content', 'c', [], 2)->required()->string();
						$write->option('based-on', 'b', [], 3)->string()->def('');
						$write->handler(function (KliArgs $args): void {
							$based_on = (string) $args->get('based-on');
							$content  = (string) $args->get('content');
							$write    = Revisions::of(%s)->write(
								(string) $args->get('key'),
								'null' === $content ? null : $content,
								'' === $based_on ? null : (int) $based_on
							);

							$this->getCli()->writeJson([
								'applied' => $write->applied,
								'number'  => $write->revision?->number,
							]);
						});

						$duplicate = $this->action('duplicate', 'Insert a revision number that is taken.');
						$duplicate->option('key', 'k', [], 1)->required()->string();
						$duplicate->option('number', 'n', [], 2)->required()->number();
						$duplicate->handler(function (KliArgs $args): void {
							try {
								OZRevision::new()
									->setNamespace(%s)
									->setKey((string) $args->get('key'))
									->setNumber((int) $args->get('number'))
									->setContent('{"taken":true}')
									->save();
								$refused = false;
							} catch (DBALUniqueViolationException) {
								$refused = true;
							}

							$this->getCli()->writeJson(['refused' => $refused]);
						});

						$current = $this->action('current', 'The current revision of every key.');
						$current->handler(function (): void {
							$this->getCli()->writeJson(['current' => \array_map(
								static fn ($revision): int => $revision->number,
								Revisions::of(%s)->currentAll()
							)]);
						});
					}
				}

				PHP,
			$ns,
			\var_export(self::NAMESPACE, true),
			\var_export(self::NAMESPACE, true),
			\var_export(self::NAMESPACE, true)
		);
	}
}
