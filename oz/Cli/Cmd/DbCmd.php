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

namespace OZONE\Core\Cli\Cmd;

use Exception;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\ORM\Generators\CSGeneratorDart;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\Generators\CSGeneratorTS;
use Gobl\ORM\ORM;
use Kli\Exceptions\KliException;
use Kli\Exceptions\KliInputException;
use Kli\Kli;
use Kli\KliArgs;
use OLIUP\CG\PHPNamespace;
use OZONE\Core\App\Db;
use OZONE\Core\App\Settings;
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Process;
use OZONE\Core\Cli\Utils\Utils;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\Utils\Random;
use PHPUtils\FS\PathUtils;
use Throwable;

/**
 * Class DbCmd.
 */
final class DbCmd extends Command
{
	/**
	 * Ensures database backup.
	 *
	 * @param Kli                              $cli
	 * @param null|\OZONE\Core\FS\FilesManager $fm
	 *
	 * @throws KliException
	 */
	public static function ensureDBBackup(Kli $cli, FilesManager $fm = null): void
	{
		$fm = $fm ?? new FilesManager();

		$cli->info('Generating database backup ...');

		$db_backup_dir = \escapeshellarg($fm->getRoot());
		$cli->executeString("db backup --dir={$db_backup_dir}");
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws KliException
	 */
	protected function describe(): void
	{
		$this->description('Manage your project database.');

		// action: build database
		$build = $this->action('build', 'To build the database, generate required classes.');

		$build->option('build-all', 'a')
			->description('To build all tables in all namespaces.')
			->bool()
			->def(false);
		$build->option('class-only', 'c')
			->description('To build classes only.')
			->bool()
			->def(false);
		$build->option('namespace', 'n')
			->description('The namespace of the tables to be generated.')
			->string()
			->pattern(PHPNamespace::NAMESPACE_PATTERN)
			->def(null);
		$build->handler($this->build(...));

		// action: ts bundle
		$ts_bundle = $this->action('ts-bundle', 'To generate entities classes for TypeScript.');
		$ts_bundle->option('dir', 'd', [], 1)
			->description('The destination directory for the bundle classes.')
			->path()
			->dir()
			->writable()
			->def('.');
		$ts_bundle->handler($this->tsBundle(...));

		// action: dart bundle
		$dart_bundle = $this->action('dart-bundle', 'To generate entities classes for Dart.');
		$dart_bundle->option('dir', 'd', [], 1)
			->description('The destination directory for the bundle classes.')
			->path()
			->dir()
			->writable()
			->def('.');
		$dart_bundle->handler($this->dartBundle(...));

		// action: generate database query
		$generate = $this->action('generate', 'Generate database file.');
		$generate->option('dir', 'd', [], 1)
			->description('The destination directory for the database file.')
			->path()
			->dir()
			->writable()
			->def('.');
		$generate->option('namespace', 'n', [], 2)
			->description('The namespace of the tables to be generated.')
			->string()
			->pattern(PHPNamespace::NAMESPACE_PATTERN)
			->def(null);
		$generate->handler($this->generate(...));

		// action: backup database
		$backup = $this->action('backup', 'Backup database.');
		$backup->option('dir', 'd', [], 1)
			->description('The destination directory for the database backup file.')
			->path()
			->dir()
			->writable()
			->def('.');
		$backup->handler($this->backup(...));

		// action: source database
		$source = $this->action('source', 'Run database query from source file.');
		$source->option('file', 'f', [], 1)
			->description('The database source file.')
			->path()
			->file();
		$source->handler($this->source(...));
	}

	/**
	 * Builds database.
	 *
	 * You should backup your database first.
	 *
	 * @param KliArgs $args
	 *
	 * @throws KliException
	 * @throws KliInputException
	 */
	private function build(KliArgs $args): void
	{
		Utils::assertProjectLoaded();

		$cli        = $this->getCli();
		$all        = (bool) $args->get('build-all');
		$class_only = (bool) $args->get('class-only');
		$namespace  = $args->get('namespace');

		$db            = db();
		$oz_db_ns      = Db::getOZoneDbNamespace();
		$project_db_ns = Db::getProjectDbNamespace();

		$ns_map = [];

		if ($namespace) {
			$ns_map[$namespace] = 1;
			$found              = $db->getTables($namespace);

			if (empty($found)) {
				throw new KliInputException(\sprintf(
					'There is no tables declared in the namespace: "%s".',
					$namespace
				));
			}
		} else {
			$ns_map[$project_db_ns] = 1;

			if ($all) {
				$ns_map[$oz_db_ns] = 1;

				// for plugins
				$tables = $db->getTables();

				foreach ($tables as $table) {
					$ns = $table->getNamespace();

					if (isset($ns_map[$ns]) || $ns === $oz_db_ns || $ns === $project_db_ns) {
						continue;
					}

					$ns_map[$ns] = 1;
				}
			}
		}

		$gen = new CSGeneratorORM($db, false, false);

		foreach ($ns_map as $ns => $_) {
			// we (re)generate classes only for tables
			// in the given namespace
			$gen->generate($db->getTables($ns), ORM::getOutputDirectory($ns));

			$cli->success(\sprintf('database classes generated: "%s".', $ns));
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
					$q_file = \sprintf('%s.sql', Random::fileName('debug-db-query'));

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
	 * @param KliArgs $args
	 *
	 * @throws KliException
	 * @throws Exception
	 */
	private function tsBundle(KliArgs $args): void
	{
		Utils::assertProjectLoaded();

		$cli    = $this->getCli();
		$dir    = $args->get('dir');
		$db     = db();
		$tables = $db->getTables();
		$gen    = new CSGeneratorTS($db, true, true);

		$gen->generate($tables, $dir);
		$cli->success('TypeScript entities bundle generated.')
			->info(PathUtils::resolve($dir, 'gobl'));
	}

	/**
	 * Creates entities Dart bundle.
	 *
	 * @param KliArgs $args
	 *
	 * @throws KliException
	 * @throws Exception
	 */
	private function dartBundle(KliArgs $args): void
	{
		Utils::assertProjectLoaded();

		$cli    = $this->getCli();
		$dir    = $args->get('dir');
		$db     = db();
		$tables = $db->getTables();
		$gen    = new CSGeneratorDart($db, true, true);

		$gen->generate($tables, $dir);
		$cli->success('Dart entities bundle generated.')
			->info(PathUtils::resolve($dir, 'gobl'));
	}

	/**
	 * Generate database file.
	 *
	 * @param KliArgs $args
	 *
	 * @throws KliException
	 */
	private function generate(KliArgs $args): void
	{
		Utils::assertProjectLoaded();

		$dir       = $args->get('dir');
		$namespace = $args->get('namespace');

		if (empty($namespace)) {
			$namespace = null;
		}
		$query = db()
			->getGenerator()
			->buildDatabase($namespace);

		$file_name = \sprintf('%s.sql', Random::fileName('db'));
		$fm        = new FilesManager($dir);
		$fm->wf($file_name, $query);

		if (\file_exists($fm->resolve($file_name))) {
			$this->getCli()
				->success('database installation file generated.')
				->writeLn($fm->resolve($file_name));
		} else {
			throw new RuntimeException('database installation file generation fails.');
		}
	}

	/**
	 * Runs database file.
	 *
	 * @param KliArgs $args
	 *
	 * @throws KliException
	 */
	private function source(KliArgs $args): void
	{
		Utils::assertDatabaseAccess();

		$file  = $args->get('file');
		$query = \file_get_contents($file);
		$cli   = $this->getCli();

		if (empty($query)) {
			$cli->error(\sprintf('the database source file (%s) is empty.', $file));

			return;
		}

		$db = db();

		try {
			$db->executeMulti($query);
			$cli->success('database updated.');
		} catch (Throwable $t) {
			throw new RuntimeException('database update fails. Open log file.', [
				'queries_file' => $file,
			], $t);
		}
	}

	/**
	 * Backup database.
	 *
	 * @param KliArgs $args
	 *
	 * @throws KliException
	 */
	private function backup(KliArgs $args): void
	{
		Utils::assertDatabaseAccess();

		$dir    = $args->get('dir');
		$cli    = $this->getCli();
		$config = Settings::load('oz.db');

		if (MySQL::NAME !== $config['OZ_DB_RDBMS']) {
			throw new RuntimeException('this work only for MySQL database.');
		}

		$fm      = new FilesManager($dir);
		$outfile = $fm->resolve(\sprintf('%s.sql', Random::fileName('backup')));
		$db_host = $config['OZ_DB_HOST'];
		$db_user = $config['OZ_DB_USER'];
		$db_pass = $config['OZ_DB_PASS'];
		$db_name = $config['OZ_DB_NAME'];

		$cmd = [
			'mysqldump',
			'-h' . $db_host,
			'-u' . $db_user,
			$db_name,
			'--result-file=' . $outfile,
			'-p' . $db_pass,
		];

		$process = new Process($cmd);

		$process->run();

		if ($process->isSuccessful()) {
			$cli->success('database backup file created.')
				->writeLn($outfile);
		} else {
			throw new RuntimeException('unable to backup database.', [
				'mysqldump_error'     => $process->getErrorOutput(),
				'mysqldump_exit_code' => $process->getExitCode(),
			]);
		}
	}
}
