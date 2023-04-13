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

use Exception;
use Gobl\DBAL\Drivers\MySQL\MySQL;
use Gobl\DBAL\Interfaces\MigrationInterface;
use Gobl\ORM\Generators\CSGeneratorDart;
use Gobl\ORM\Generators\CSGeneratorORM;
use Gobl\ORM\Generators\CSGeneratorTS;
use Kli\Exceptions\KliInputException;
use Kli\Kli;
use Kli\KliArgs;
use Kli\Table\KliTableFormatter;
use OZONE\OZ\Cli\Command;
use OZONE\OZ\Cli\Process;
use OZONE\OZ\Cli\Utils\Utils;
use OZONE\OZ\Core\Configs;
use OZONE\OZ\Core\DbManager;
use OZONE\OZ\Core\Hasher;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\FS\FilesManager;
use OZONE\OZ\Migration\MigrationsManager;
use PHPUtils\FS\PathUtils;
use Throwable;

/**
 * Class Db.
 */
final class Db extends Command
{
	/**
	 * Ensures database backup.
	 *
	 * @param \Kli\Kli                       $cli
	 * @param null|\OZONE\OZ\FS\FilesManager $fm
	 *
	 * @throws \Kli\Exceptions\KliException
	 */
	public static function ensureDBBackup(Kli $cli, FilesManager $fm = null): void
	{
		$fm = $fm ?? new FilesManager(OZ_PROJECT_DIR);

		$cli->info('Generating database backup ...');

		$db_backup_dir = \escapeshellarg($fm->getRoot());
		$cli->executeString("db backup --dir={$db_backup_dir}");
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Kli\Exceptions\KliException
	 */
	protected function describe(): void
	{
		$this->description('Manage your project database.');
		$namespace_pattern = '#^[a-zA-Z][a-zA-Z0-9_]*(?:\\\\[a-zA-Z][a-zA-Z0-9_]*)*$#';

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
			->pattern($namespace_pattern)
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
			->pattern($namespace_pattern)
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

		// action: db migration:create
		$this->action('migrations:create', 'Create database migrations.')
			->handler($this->migrationsCreate(...));

		// action: db migration:check
		$this->action('migrations:check', 'Check database migrations.')
			->handler($this->migrationsCheck(...));

		// action: db migration:run
		$migrationRun = $this->action('migrations:run', 'Run database migrations.');
		$migrationRun->handler($this->migrationsRun(...));

		// action: db migration:rollback
		$migrationRollback = $this->action('migrations:rollback', 'Rollback database migrations.');
		$migrationRollback->option('to-version')
			->description('The migration version to rollback to.')
			->required()
			->number(0);
		$migrationRollback->handler($this->migrationsRollback(...));

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
	 * @param \Kli\KliArgs $args
	 *
	 * @throws \Kli\Exceptions\KliException
	 * @throws \Kli\Exceptions\KliInputException
	 */
	private function build(KliArgs $args): void
	{
		Utils::assertProjectFolder();

		$cli        = $this->getCli();
		$all        = (bool) $args->get('build-all');
		$class_only = (bool) $args->get('class-only');
		$namespace  = $args->get('namespace');

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

		$gen = new CSGeneratorORM($db, false, false);

		foreach ($map as $ns => $_) {
			$dir = $default_dir[$ns] ?? $plugins_out_dir;

			// we (re)generate classes only for tables
			// in the given namespace
			$tables = $db->getTables($ns);
			$gen->generate($tables, $dir);

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
	 * @param \Kli\KliArgs $args
	 *
	 * @throws \Kli\Exceptions\KliException
	 * @throws Exception
	 */
	private function tsBundle(KliArgs $args): void
	{
		Utils::assertProjectFolder();

		$cli    = $this->getCli();
		$dir    = $args->get('dir');
		$db     = DbManager::getDb();
		$tables = $db->getTables();
		$gen    = new CSGeneratorTS($db, true, true);

		$gen->generate($tables, $dir);
		$cli->success('TypeScript entities bundle generated.')
			->info(PathUtils::resolve($dir, 'gobl'));
	}

	/**
	 * Creates entities Dart bundle.
	 *
	 * @param \Kli\KliArgs $args
	 *
	 * @throws \Kli\Exceptions\KliException
	 * @throws Exception
	 */
	private function dartBundle(KliArgs $args): void
	{
		Utils::assertProjectFolder();

		$cli    = $this->getCli();
		$dir    = $args->get('dir');
		$db     = DbManager::getDb();
		$tables = $db->getTables();
		$gen    = new CSGeneratorDart($db, true, true);

		$gen->generate($tables, $dir);
		$cli->success('Dart entities bundle generated.')
			->info(PathUtils::resolve($dir, 'gobl'));
	}

	/**
	 * Generate database file.
	 *
	 * @param \Kli\KliArgs $args
	 *
	 * @throws \Kli\Exceptions\KliException
	 */
	private function generate(KliArgs $args): void
	{
		Utils::assertProjectFolder();

		$dir       = $args->get('dir');
		$namespace = $args->get('namespace');

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
				->success('database installation file generated.')
				->writeLn($fm->resolve($file_name));
		} else {
			throw new RuntimeException('database installation file generation fails.');
		}
	}

	/**
	 * Runs database file.
	 *
	 * @param \Kli\KliArgs $args
	 *
	 * @throws \Kli\Exceptions\KliException
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

		$db = DbManager::getDb();

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
	 * @param \Kli\KliArgs $args
	 *
	 * @throws \Kli\Exceptions\KliException
	 */
	private function backup(KliArgs $args): void
	{
		Utils::assertDatabaseAccess();

		$dir    = $args->get('dir');
		$cli    = $this->getCli();
		$config = Configs::load('oz.db');

		if (MySQL::NAME !== $config['OZ_DB_RDBMS']) {
			throw new RuntimeException('this work only for MySQL database.');
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
			throw new RuntimeException('unable to backup database.', [
				'mysqldump_error_message' => $error,
			]);
		}
	}

	/**
	 * Creates migration file.
	 */
	private function migrationsCreate(): void
	{
		$mg   = new MigrationsManager();
		$path = $mg->createMigration();

		if ($path) {
			$this->getCli()
				->success('Migration file created.')
				->writeLn($path);
		} else {
			$this->getCli()
				->info('No changes detected.');
		}
	}

	/**
	 * Checks pending migrations.
	 */
	private function migrationsCheck(): void
	{
		$mg  = new MigrationsManager();
		$cli = $this->getCli();
		if ($mg->hasPendingMigrations()) {
			$cli->info('There are pending migrations.');

			$table = $cli->table();
			$table->addHeader('Label', 'label');
			$table->addHeader('Version', 'version');
			$table->addHeader('Date', 'date')
				->setCellFormatter(KliTableFormatter::date('jS F Y, g:i:s a'));

			$table->addRows(\array_map(static function (MigrationInterface $migration) {
				return [
					'label'   => $migration->getLabel(),
					'version' => $migration->getVersion(),
					'date'    => $migration->getTimestamp(),
				];
			}, $mg->getPendingMigrations()));

			$cli->writeLn($table->render(), false);
		} else {
			$cli->success('There are no pending migrations.');
		}
	}

	/**
	 * Runs pending migrations.
	 *
	 * @param \Kli\KliArgs $args
	 *
	 * @throws \Kli\Exceptions\KliException
	 */
	private function migrationsRun(KliArgs $args): void
	{
		$mg         = new MigrationsManager();
		$migrations = $mg->getPendingMigrations();

		if (empty($migrations)) {
			$this->getCli()
				->info('There are no pending migrations.');

			return;
		}

		// backup db before running migrations
		self::ensureDBBackup($this->getCli());
		foreach ($migrations as $migration) {
			$this->getCli()
				->info(\sprintf(
					'Running migration "%s" generated on "%s" ...',
					$migration->getLabel(),
					\date('jS F Y, g:i:s a', $migration->getTimestamp())
				));

			try {
				$mg->runMigration($migration);
			} catch (Throwable $t) {
				$error_message = \sprintf(
					'Migration "%s" generated on "%s" failed.',
					$migration->getLabel(),
					\date('jS F Y, g:i:s a', $migration->getTimestamp())
				);

				throw new RuntimeException($error_message, [
					'label'   => $migration->getLabel(),
					'version' => $migration->getVersion(),
				], $t);
			}
		}
	}

	/**
	 * Rolls back migrations.
	 *
	 * @param \Kli\KliArgs $args
	 *
	 * @throws \Kli\Exceptions\KliException
	 */
	private function migrationsRollback(KliArgs $args): void
	{
		$mg                 = new MigrationsManager();
		$target_version     = $args->get('to-version');
		$current_db_version = $mg->getCurrentDbVersion();

		// make sure target version is less than current version
		if ($target_version >= $current_db_version) {
			$this->getCli()
				->info(\sprintf('Target version "%s" is not less than current version "%s".', $target_version, $current_db_version));

			return;
		}

		// gets all migration between target version and current db version
		$migrations = $mg->getMigrationBetween($target_version, $current_db_version);

		if (empty($migrations)) {
			$this->getCli()
				->info(\sprintf('There are no migrations to rollback from current version "%s" to "%s".', $current_db_version, $target_version));

			return;
		}

		$cli = $this->getCli();

		$cli->info(\sprintf('Rolling back migrations from current version "%s" to "%s" ...', $current_db_version, $target_version));

		// backup db before migrations
		self::ensureDBBackup($this->getCli());

		$cli->info('This may take a while ...');

		$migrations = \array_reverse($migrations);

		foreach ($migrations as $migration) {
			if ($migration->getVersion() !== $target_version) {
				$cli->info(\sprintf(
					'Rolling back migration "%s" generated on "%s" ...',
					$migration->getLabel(),
					\date('jS F Y, g:i:s a', $migration->getTimestamp())
				));

				try {
					$mg->rollbackMigration($migration);
				} catch (Throwable $e) {
					throw new RuntimeException('Error rolling back migration.', [
						'label'   => $migration->getLabel(),
						'version' => $migration->getVersion(),
					], $e);
				}
			}
		}

		$cli->success('Migrations rolled back successfully.');
	}
}
