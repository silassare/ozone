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

namespace OZONE\Core\Migrations\Cli;

use Gobl\DBAL\Interfaces\MigrationInterface;
use Kli\Exceptions\KliException;
use Kli\KliArgs;
use Kli\Table\KliTableFormatter;
use OZONE\Core\Cli\Cmd\DbCmd;
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Utils\Utils;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Migrations\Migrations;
use Throwable;

/**
 * Class MigrationsCmd.
 */
final class MigrationsCmd extends Command
{
	/**
	 * {@inheritDoc}
	 *
	 * @throws KliException
	 */
	protected function describe(): void
	{
		$this->description('Manage your project database.');

		$create = $this->action('create', 'Create database migrations.')
			->handler($this->create(...));
		$create->option('force', 'f')
			->description('Force creation even if no changes is detected by the diff algorithm.')
			->bool();
		$create->option('label', 'l')
			->description('The migration label.')
			->prompt(true, 'Enter migration label')
			->string();

		$this->action('check', 'Check database migrations.')
			->handler($this->check(...));

		$this->action('run', 'Run database migrations.')
			->handler($this->run(...));

		$this->action('rollback', 'Rollback database migrations.')
			->handler($this->rollback(...))
			->option('to-version')
			->description('The migration version to rollback to.')
			->required()
			->number(0);
	}

	/**
	 * Creates migration file.
	 *
	 * @throws KliException
	 */
	private function create(KliArgs $args): void
	{
		Utils::assertProjectLoaded();
		$force = (bool) $args->get('force');
		$label = (string) $args->get('label');

		$mg   = new Migrations();
		$path = $mg->create($force, empty($label) ? null : $label);

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
	private function check(): void
	{
		Utils::assertProjectLoaded();

		$mg  = new Migrations();
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
	 * @throws KliException
	 */
	private function run(): void
	{
		Utils::assertDatabaseAccess();

		$mg         = new Migrations();
		$migrations = $mg->getPendingMigrations();

		if (empty($migrations)) {
			$this->getCli()
				->info('There are no pending migrations.');

			return;
		}

		// backup db before running migrations
		DbCmd::ensureDBBackup($this->getCli());

		foreach ($migrations as $migration) {
			$this->getCli()
				->info(\sprintf(
					'Running migration "%s" generated on "%s" ...',
					$migration->getLabel(),
					\date('jS F Y, g:i:s a', $migration->getTimestamp())
				), false);

			try {
				$mg->run($migration);
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
	 * @param KliArgs $args
	 *
	 * @throws KliException
	 */
	private function rollback(KliArgs $args): void
	{
		Utils::assertDatabaseAccess();

		$mg                 = new Migrations();
		$target_version     = $args->get('to-version');
		$current_db_version = $mg::getCurrentDbVersion();

		// make sure target version is less than current version
		if ($target_version >= $current_db_version) {
			$this->getCli()
				->info(\sprintf(
					'Target version "%s" is not less than current version "%s".',
					$target_version,
					$current_db_version
				));

			return;
		}

		// gets all migration between target version and current db version
		$migrations = $mg->getMigrationBetween($target_version, $current_db_version);

		if (empty($migrations)) {
			$this->getCli()
				->info(\sprintf(
					'There are no migrations to rollback from current version "%s" to "%s".',
					$current_db_version,
					$target_version
				));

			return;
		}

		$cli = $this->getCli();

		$cli->info(\sprintf(
			'Rolling back migrations from current version "%s" to "%s" ...',
			$current_db_version,
			$target_version
		));

		// backup db before migrations
		DbCmd::ensureDBBackup($this->getCli());

		$cli->info('This may take a while ...');

		$migrations = \array_reverse($migrations);

		foreach ($migrations as $migration) {
			if ($migration->getVersion() !== $target_version) {
				$cli->info(\sprintf(
					'%s => %s ...',
					\date('jS F Y, g:i:s a', $migration->getTimestamp()),
					$migration->getLabel(),
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
