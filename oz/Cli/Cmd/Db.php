<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Cli\Cmd;

use Exception;
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
use OZONE\OZ\Core\DbManager;
use OZONE\OZ\Core\Hasher;
use OZONE\OZ\Core\SettingsManager;
use OZONE\OZ\FS\FilesManager;
use OZONE\OZ\FS\PathUtils;

final class Db extends Command
{
	/**
	 * @inheritdoc
	 *
	 * @throws \Exception
	 */
	public function execute(KliAction $action, array $options, array $anonymous_options)
	{
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
	 * @inheritdoc
	 *
	 * @throws \Kli\Exceptions\KliException
	 */
	protected function describe()
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
				'#^(?:[a-zA-Z][a-zA-Z0-9_]*(?:\\\\[a-zA-Z][a-zA-Z0-9_]*)*)$#',
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
	 * @throws \OZONE\OZ\Exceptions\BaseException
	 * @throws \Exception
	 */
	private function build(array $options)
	{
		Utils::assertDatabaseAccess();

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
				$dir = isset($default_dir[$ns]) ? $default_dir[$ns] : $plugins_out_dir;

				// we (re)generate classes only for tables
				// in the given namespace
				$tables = $db->getTables($ns);
				$gen->generate($tables, $dir);

				$cli->success(\sprintf('database classes generated: "%s".', $ns));
			}
		}

		$queries = '';

		if (!$class_only) {
			try {
				$queries = $db->buildDatabase();
				$db->executeMulti($queries);

				$cli->success('database queries executed.');
			} catch (Exception $e) {
				$cli->error('database queries execution failed. Open log file.')
					->log($e);

				if (!empty($queries)) {
					$queries_file = \sprintf('%s.sql', Hasher::genRandomFileName('debug-db-query'));

					\file_put_contents($queries_file, $queries);

					$msg = \sprintf('see queries in: %s', $queries_file);
					$cli->info($msg)
						->log($msg);
				}
			}
		}
	}

	/**
	 * Creates entities TypeScript bundle.
	 *
	 * @param array $options
	 *
	 * @throws \OZONE\OZ\Exceptions\BaseException
	 * @throws \Exception
	 */
	private function tsBundle(array $options)
	{
		Utils::assertDatabaseAccess();

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
	 * @throws \OZONE\OZ\Exceptions\BaseException
	 * @throws \Exception
	 */
	private function dartBundle(array $options)
	{
		Utils::assertDatabaseAccess();

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
	 *
	 * @throws \OZONE\OZ\Exceptions\BaseException
	 */
	private function generate(array $options)
	{
		Utils::assertProjectFolder();

		$dir       = $options['d'];
		$namespace = $options['n'];

		if (empty($namespace)) {
			$namespace = null;
		}
		$query = DbManager::getDb()
						  ->buildDatabase($namespace);

		$file_name = \sprintf('%s.sql', Hasher::genRandomFileName('db'));
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
	 *
	 * @throws \OZONE\OZ\Exceptions\BaseException
	 */
	private function source(array $options)
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
		} catch (Exception $e) {
			$cli->error('database update fails. Open log file.')
				->log($e);
		}
	}

	/**
	 * Backup database.
	 *
	 * @param array $options
	 *
	 * @throws \OZONE\OZ\Exceptions\BaseException
	 */
	private function backup(array $options)
	{
		Utils::assertDatabaseAccess();

		$dir    = $options['d'];
		$cli    = $this->getCli();
		$config = SettingsManager::get('oz.db');

		if ($config['OZ_DB_RDBMS'] !== \Gobl\DBAL\Db::MYSQL) {
			$cli->error('this work only for MySQL database.');

			return;
		}
		$fm      = new FilesManager($dir);
		$outfile = $fm->resolve(\sprintf('%s.sql', Hasher::genRandomFileName('backup')));
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
		$process = new Process($cmd, null);

		$process->open();

		$error     = $process->readStderr();
		$exit_code = $process->close();

		if ($exit_code === 0) {
			$cli->success('database backup file created.')
				->writeLn($outfile);
		} else {
			$cli->error('unable to backup database.')
				->error($error);
		}
	}
}
