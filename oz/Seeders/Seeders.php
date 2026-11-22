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

namespace OZONE\Core\Seeders;

use Gobl\DBAL\Table;
use Gobl\ORM\ORMEntity;
use Gobl\ORM\ORMOptions;
use Gobl\ORM\ORMTableQuery;
use Gobl\ORM\Utils\ORMClassKind;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Seeders\Interfaces\SeederInterface;

/**
 * Class Seeders.
 *
 * Runs the seeders `oz.seeders` lists, and holds the one rule every seeder follows.
 */
final class Seeders
{
	/**
	 * Runs the enabled seeders, each in a transaction of its own, the demo ones only when asked for.
	 *
	 * @return list<array{seeder: class-string<SeederInterface>, demo: bool, written: int}>
	 */
	public static function run(bool $demo = false): array
	{
		$report = [];

		foreach (Settings::load('oz.seeders') as $class => $enabled) {
			if (!$enabled) {
				continue;
			}

			if (!\is_string($class) || !\is_subclass_of($class, SeederInterface::class)) {
				throw new RuntimeException(\sprintf(
					'Seeder "%s" in oz.seeders should implement "%s".',
					(string) $class,
					SeederInterface::class
				));
			}

			if ($class::isDemo() && !$demo) {
				continue;
			}

			$written = (int) db()->runInTransaction(static fn (): int => new $class()->run());

			$report[] = ['seeder' => $class, 'demo' => $class::isDemo(), 'written' => $written];
		}

		return $report;
	}

	/**
	 * Inserts the rows no row of the table has the key of yet, and answers how many it inserted.
	 *
	 * The rule a seeder follows: a row is found by its key, a stable value the data source gives it
	 * (a term's code, a template's name), and one that exists is never updated -- run on every deploy,
	 * a seeder that updated would undo what an administrator changed since. Each row goes in through
	 * the table's entity, so its CRUD rules run as for any other write.
	 *
	 * @param list<string>                     $key  the full names of the columns a row is found by
	 * @param iterable<array<string, mixed>> $rows each by full column name, holding the key's columns
	 */
	public static function insertMissing(Table $table, array $key, iterable $rows): int
	{
		/** @var class-string<ORMTableQuery<ORMEntity>> $query */
		$query = ORMClassKind::QUERY->getClassFQN($table);
		/** @var class-string<ORMEntity> $entity */
		$entity  = ORMClassKind::ENTITY->getClassFQN($table);
		$written = 0;

		foreach ($rows as $row) {
			$filters = [];

			foreach ($key as $column) {
				if (!\array_key_exists($column, $row)) {
					throw new RuntimeException(\sprintf(
						'A row to seed into "%s" has no value for its key column "%s".',
						$table->getName(),
						$column
					));
				}

				if ([] !== $filters) {
					$filters[] = 'and';
				}

				$filters[] = [$column, 'eq', $row[$column]];
			}

			$found = $query::new()->where($filters)->find(ORMOptions::makePaginated(1, 1))->fetchClass();

			if (null === $found) {
				$entity::new()->hydrate($row)->save();
				++$written;
			}
		}

		return $written;
	}
}
