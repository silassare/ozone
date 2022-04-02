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

namespace OZONE\OZ\Cli\Cmd;

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\ORM\Generators\GeneratorDart;
use Gobl\ORM\Generators\GeneratorORM;
use Gobl\ORM\Generators\GeneratorTS;
use Kli\Exceptions\KliInputException;
use Kli\KliAction;
use Kli\KliOption;
use Kli\Types\KliTypeBool;
use Kli\Types\KliTypePath;
use Kli\Types\KliTypeString;
use OZONE\OZ\Cli\Command;
use OZONE\OZ\Cli\Process;
use OZONE\OZ\Cli\Utils\Utils;
use OZONE\OZ\Core\Configs;
use OZONE\OZ\Core\DbManager;
use OZONE\OZ\Core\Hasher;
use OZONE\OZ\Db\OZUsersFilters;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\FS\FilesManager;
use PHPUtils\FS\PathUtils;
use Throwable;

/**
 * Class Db.
 */
final class Db extends Command
{
	/**
	 * {@inheritDoc}
	 *
	 * @param \Kli\KliAction $action
	 * @param array          $options
	 * @param array          $anonymous_options
	 *
	 * @throws Throwable
	 */
	public function execute(KliAction $action, array $options, array $anonymous_options): void
	{
		if (0) {
			$uq = new OZUsersFilters();
			if (!0) {
				$qb = $uq->whereCc2Is('bj')
					->and(
						function (OZUsersFilters $sub) {
							 	return $sub->whereIdIsGte(10)
							 		->and()
							 		->whereIdIsLte(20)
							 		->or()
							 		->whereValidIsTrue();
							 }
					)
					->getTableQuery()
					->select(100);
			} else {
				$qb = $uq->where([
					'cc2',
					'eq',
					'bj',
					'and',
					[['id', 'gte', 10, 'and', 'id', 'lte', 20], 'or', 'valid', 'is_true'],
				])
					->getTableQuery()
					->select(100);
			}
			oz_logger([
				'query'  => $qb->getSqlQuery(),
				'values' => $qb->getBoundValues(),
				'types'  => $qb->getBoundValuesTypes(),
			]);
		}

		switch ($action->getName()) {
			case 'build':
				$this->build($options);

				break;

			case 'ts-bundle':
				$this->tsBundle($options);

				break;

			case 'dart-bundle':
				$this->dartBundle($options);

				break;

			case 'backup':
				$this->backup($options);

				break;

			case 'generate':
				$this->generate($options);

				break;

			case 'source':
				$this->source($options);

				break;
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Kli\Exceptions\KliException
	 */
	protected function describe(): void
	{
		$this->description('Manage your project database.');

		// action: build database
		$build = new KliAction('build');
		$build->description('To build the database, generate required classes.');

		// action: ts bundle
		$ts_bundle = new KliAction('ts-bundle');
		$ts_bundle->description('To generate entities classes for TypeScript.');

		// action: dart bundle
		$dart_bundle = new KliAction('dart-bundle');
		$dart_bundle->description('To generate entities classes for Dart.');

		// action: generate database query
		$generate = new KliAction('generate');
		$generate->description('Generate database file.');

		// action: backup database
		$backup = new KliAction('backup');
		$backup->description('Backup database.');

		// option: -a alias --build-all
		$all = new KliOption('a');
		$all->alias('build-all')
			->type(new KliTypeBool())
			->def(false)
			->description('To rebuild all tables in all namespaces.');

		// option: -c alias --class-only
		$class_only = (new KliOption('c'))
			->alias('class-only')
			->type(new KliTypeBool())
			->def(false)
			->description('To rebuild classes only.');

		// option: -n alias --namespace
		$n = (new KliOption('n'))
			->alias('namespace')
			->offsets(1)
			->type((new KliTypeString())->pattern(
				'#^[a-zA-Z][a-zA-Z0-9_]*(?:\\\\[a-zA-Z][a-zA-Z0-9_]*)*$#',
				'You should provide valid php namespace.'
			))
			->def(null)
			->description('The namespace of the tables to be generated.');

		// action: source database
		$source = (new KliAction('source'))->description('Run database query from source file.');

		// option: -d alias --dir
		$d = (new KliOption('d'))
			->alias('dir')
			->offsets(1)
			->type((new KliTypePath())
			->dir()
			->writable())
			->def('.')
			->description('The destination directory for the database classes.');

		// option: -d alias --dir
		$b_d = (new KliOption('d'))
			->alias('dir')
			->offsets(1)
			->type((new KliTypePath())
			->dir()
			->writable())
			->def('.')
			->description('The destination directory for the bundle classes.');

		$f = new KliOption('f');
		$f->alias('file')
			->offsets(1)
			->type((new KliTypePath())->file())
			->description('The database source file to run.');

		$d_c = clone $d;
		$build->addOption($all, $n, $class_only);
		$generate->addOption($n, $d_c->offsets(2));
		$backup->addOption($d);
		$ts_bundle->addOption($b_d);
		$dart_bundle->addOption($b_d);
		$source->addOption($f);

		$this->addAction($build, $ts_bundle, $dart_bundle, $generate, $backup, $source);
	}

	/**
	 * Builds database.
	 *
	 * You should backup your database first.
	 *
	 * @param array $options
	 *
	 * @throws Throwable
	 */
	private function build(array $options): void
	{
		Utils::assertProjectFolder();

		$cli        = $this->getCli();
		$all        = (bool) $options['a'];
		$class_only = (bool) $options['c'];
		$namespace  = $options['n'];

		$structure = DbManager::getProjectDbDirectoryStructure();

		$db            = DbManager::getDb();
		$oz_db_ns      = $structure['oz_db_namespace'];
		$project_db_ns = $structure['project_db_namespace'];
		$default_dir   = [
			$oz_db_ns      => $structure['oz_db_folder'],
			$project_db_ns => $structure['project_db_folder'],
		];

		$map             = [];
		$plugins_out_dir = $structure['project_db_folder'];

		if ($namespace) {
			$map[$namespace] = 1;
			$found           = $db->getTables($namespace);

			if (empty($found)) {
				throw new KliInputException(\sprintf(
					'There is no tables declared in the namespace: "%s".',
					$namespace
				));
			}
		} else {
			$map[$project_db_ns] = 1;

			if ($all) {
				$map[$oz_db_ns] = 1;

				// for plugins
				$tables = $db->getTables();

				foreach ($tables as $table) {
					$ns = $table->getNamespace();

					if (isset($map[$ns]) || $ns === $oz_db_ns || $ns === $project_db_ns) {
						continue;
					}

					$map[$ns] = 1;
				}
			}
		}

		$gen = new GeneratorORM($db, false, false);

		foreach ($map as $ns => $ok) {
			if ($ok) {
				$dir = $default_dir[$ns] ?? $plugins_out_dir;

				// we (re)generate classes only for tables
				// in the given namespace
				$tables = $db->getTables($ns);
				$gen->generate($tables, $dir);

				$cli->success(\sprintf('database classes generated: "%s".', $ns));
			}
		}

		$queries = '';

		if (!$class_only) {
			Utils::assertDatabaseAccess();

			try {
				$queries = $db->getGenerator()
					->buildDatabase();
				$db->executeMulti($queries);

				$cli->success('database queries executed.');
			} catch (Throwable $t) {
				$error_data = [];
				if (!empty($queries)) {
					$q_file = \sprintf('%s.sql', Hasher::genFileName('debug-db-query'));

					\file_put_contents($q_file, $queries);

					$error_data['queries_file'] = $q_file;
				}

				throw new RuntimeException('database queries execution failed.', $error_data, $t);
			}
		}
	}

	/**
	 * Creates entities TypeScript bundle.
	 *
	 * @param array $options
	 *
	 * @throws Throwable
	 */
	private function tsBundle(array $options): void
	{
		Utils::assertProjectFolder();

		$cli    = $this->getCli();
		$dir    = $options['d'];
		$db     = DbManager::getDb();
		$tables = $db->getTables();
		$gen    = new GeneratorTS($db, true, true);

		$gen->generate($tables, $dir);
		$cli->success('TypeScript entities bundle generated.')
			->info(PathUtils::resolve($dir, 'gobl'));
	}

	/**
	 * Creates entities Dart bundle.
	 *
	 * @param array $options
	 *
	 * @throws Throwable
	 */
	private function dartBundle(array $options): void
	{
		Utils::assertProjectFolder();

		$cli    = $this->getCli();
		$dir    = $options['d'];
		$db     = DbManager::getDb();
		$tables = $db->getTables();
		$gen    = new GeneratorDart($db, true, true);

		$gen->generate($tables, $dir);
		$cli->success('Dart entities bundle generated.')
			->info(PathUtils::resolve($dir, 'gobl'));
	}

	/**
	 * Generate database file.
	 *
	 * @param array $options
	 */
	private function generate(array $options): void
	{
		Utils::assertProjectFolder();

		$dir       = $options['d'];
		$namespace = $options['n'];

		if (empty($namespace)) {
			$namespace = null;
		}
		$query = DbManager::getDb()
			->getGenerator()
			->buildDatabase($namespace);

		$file_name = \sprintf('%s.sql', Hasher::genFileName('db'));
		$fm        = new FilesManager($dir);
		$fm->wf($file_name, $query);

		if (\file_exists($fm->resolve($file_name))) {
			$this->getCli()
				->success('database file generated.')
				->writeLn($fm->resolve($file_name));
		} else {
			$this->getCli()
				->error('database file generation fails.');
		}
	}

	/**
	 * Runs database file.
	 *
	 * @param array $options
	 */
	private function source(array $options): void
	{
		Utils::assertDatabaseAccess();

		$f     = $options['f'];
		$query = \file_get_contents($f);
		$cli   = $this->getCli();

		if (empty($query)) {
			$cli->error(\sprintf('the database source file (%s) is empty.', $f));

			return;
		}

		$db = DbManager::getDb();

		try {
			$db->executeMulti($query);
			$cli->success('database updated.');
		} catch (Throwable $t) {
			throw new RuntimeException('database update fails. Open log file.', [
				'queries_file' => $f,
			], $t);
		}
	}

	/**
	 * Backup database.
	 *
	 * @param array $options
	 */
	private function backup(array $options): void
	{
		Utils::assertDatabaseAccess();

		$dir    = $options['d'];
		$cli    = $this->getCli();
		$config = Configs::load('oz.db');

		if (RDBMSInterface::MYSQL !== $config['OZ_DB_RDBMS']) {
			$cli->error('this work only for MySQL database.');

			return;
		}
		$fm      = new FilesManager($dir);
		$outfile = $fm->resolve(\sprintf('%s.sql', Hasher::genFileName('backup')));
		$db_host = \escapeshellarg($config['OZ_DB_HOST']);
		$db_user = \escapeshellarg($config['OZ_DB_USER']);
		$db_pass = \escapeshellarg($config['OZ_DB_PASS']);
		$db_name = \escapeshellarg($config['OZ_DB_NAME']);
		$cmd     = \sprintf(
			'mysqldump -h%s -u%s %s --result-file=%s -p%s',
			$db_host,
			$db_user,
			$db_name,
			\escapeshellarg($outfile),
			$db_pass
		);

		$process = new Process($cmd);

		$process->open();

		$error     = $process->readStderr();
		$exit_code = $process->close();

		if (0 === $exit_code) {
			$cli->success('database backup file created.')
				->writeLn($outfile);
		} else {
			$cli->error('unable to backup database.')
				->error($error);
		}
	}
}
