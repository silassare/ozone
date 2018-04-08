<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Cli\Cmd;

	use Gobl\ORM\ORM;
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
	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\FS\FilesManager;

	final class Db extends Command
	{
		/**
		 * {@inheritdoc}
		 */
		public function execute(KliAction $action, array $options, array $anonymous_options)
		{
			switch ($action->getName()) {
				case 'refresh':
					$this->refresh($options);
					break;
				case 'build':
					$this->build($options);
					break;
				case 'generate':
					$this->generate($options);
					break;
				case 'backup':
					$this->backup($options);
					break;
				case 'source':
					$this->source($options);
					break;
			}
		}

		/**
		 * Build database.
		 *
		 * You should backup your database first.
		 *
		 * @param array $options
		 *
		 * @throws \Kli\Exceptions\KliInputException
		 */
		private function build(array $options)
		{
			Utils::assertDatabaseAccess();

			$cli       = $this->getCli();
			$all       = (bool)$options['a'];
			$structure = DbManager::getProjectDbDirectoryStructure();

			$db     = DbManager::getInstance();
			$tables = $db->getTables();

			$map = [
				$structure['oz_db_namespace']      => $structure['oz_db_folder'],
				$structure['project_db_namespace'] => $structure['project_db_folder']
			];

			// for plugins
			foreach ($tables as $table) {
				$ns = $table->getNamespace();

				if (isset($map[$ns])) {
					continue;
				}

				$map[$ns] = $structure['project_db_folder'];
			}

			$gen = ORM::getClassGenerator();

			foreach ($map as $ns => $dir) {
				if ($all === false AND $ns === $structure['oz_db_namespace']) {
					continue;
				}
				// we (re)generate classes only for tables
				// in the given namespace
				$gen->generateORMClasses($ns, $dir);
			}

			try {
				$query = $db->generateDatabaseQuery();
				// oz_logger($query);
				$db->multipleQueryExecute($query);
				$cli->writeLn('Success: database build done.');
			} catch (\Exception $e) {
				oz_logger($e);
				$cli->writeLn('Error: database build fails. Open log file.');
			}
		}

		/**
		 * Refresh database.
		 *
		 * You should backup your database first.
		 *
		 * @param array $options
		 *
		 * @throws \Kli\Exceptions\KliInputException
		 */
		private function refresh(array $options)
		{
			Utils::assertDatabaseAccess();

			$cli       = $this->getCli();
			$namespace = $options['n'];
			$dir       = $options['d'];

			$structure = DbManager::getProjectDbDirectoryStructure();

			if ($namespace === $structure['oz_db_namespace']) {
				$dir = $structure['oz_db_folder'];
			}

			if (empty($namespace) OR empty($dir)) {
				$namespace = empty($namespace) ? $structure['project_db_namespace'] : $namespace;
				$dir       = empty($dir) ? $structure['project_db_folder'] : $dir;
			}

			$db    = DbManager::getInstance();
			$found = $db->getTables($namespace);
			if (empty($found)) {
				throw new KliInputException(sprintf('There is no tables declared with "%s" namespace.', $namespace));
			}

			$query = $db->generateDatabaseQuery($namespace);
			// we (re)generate classes only for tables
			// in the given namespace
			$gen = ORM::getClassGenerator();
			$gen->generateORMClasses($namespace, $dir);

			try {
				$db->multipleQueryExecute($query);
				$cli->writeLn('Success: database refreshed.');
			} catch (\Exception $e) {
				oz_logger($e);
				$cli->writeLn('Error: database refresh fails. Open log file.');
			}
		}

		/**
		 * Generate database file.
		 *
		 * @param array $options
		 */
		private function generate(array $options)
		{
			Utils::assertProjectFolder();

			$dir       = $options['d'];
			$query     = DbManager::getInstance()
								  ->generateDatabaseQuery();
			$file_name = sprintf('database-%s.sql', time());
			$fm        = new FilesManager($dir);
			$fm->wf($file_name, $query);

			if (file_exists($fm->resolve($file_name))) {
				$this->getCli()
					 ->writeLn('Success: database file generated.')
					 ->writeLn($fm->resolve($file_name));
			} else {
				$this->getCli()
					 ->writeLn('Error: database file generation fails.');
			}
		}

		/**
		 * Run database file.
		 *
		 * @param array $options
		 */
		private function source(array $options)
		{
			Utils::assertDatabaseAccess();

			$f     = $options['f'];
			$query = file_get_contents($f);
			$cli   = $this->getCli();

			if (empty($query)) {
				$cli->writeLn(sprintf('Error: the sql source file(%s) is empty.', $f));

				return;
			}

			$db = DbManager::getInstance();

			try {
				$db->multipleQueryExecute($query);
				$cli->writeLn('Success: database updated.');
			} catch (\Exception $e) {
				oz_logger($e);
				$cli->writeLn('Error: database update fails. Open log file.');
			}
		}

		/**
		 * Backup database.
		 *
		 * @param array $options
		 */
		private function backup(array $options)
		{
			Utils::assertDatabaseAccess();

			$dir = $options['d'];
			$cli = $this->getCli();

			// TODO each rdbms should have its own method to dump database
			$cli->writeLn('Warning: this work only for MySQL database.');

			$fm          = new FilesManager($dir);
			$config      = SettingsManager::get('oz.db');
			$db_host     = escapeshellarg($config['OZ_DB_HOST']);
			$db_user     = escapeshellarg($config['OZ_DB_USER']);
			$db_pass     = escapeshellarg($config['OZ_DB_PASS']);
			$db_name     = escapeshellarg($config['OZ_DB_NAME']);
			$outfile     = $fm->resolve(sprintf('backup-%s.sql', time()));
			$cmd         = "mysqldump -h {$db_host} -u {$db_user} --password={$db_pass} {$db_name}";
			$process     = new Process($cmd);
			$return_code = $process->run()
								   ->close();
			if ($return_code === 0) {
				$fm->wf($outfile, $process->getOutput());
				$cli->writeLn('Success: database backup file created.')
					->writeLn($outfile);
			} else {
				$cli->writeLn('Error: unable to backup database.')
					->writeLn($process->getError());
			}
		}

		/**
		 * {@inheritdoc}
		 */
		protected function describe()
		{
			$this->description("Manage your project database.");

			// action: build database
			$build = new KliAction('build');
			$build->description('To build the entire database and generate required classes.');

			// action: generate database query
			$generate = new KliAction('generate');
			$generate->description('Generate database file.');

			// action: backup database
			$backup = new KliAction('backup');
			$backup->description('Backup database.');

			// action: refresh database
			$refresh = new KliAction('refresh');
			$refresh->description('Refresh database and regenerate table row classes.');
			// option: -a alias --build-all
			$all = new KliOption('a');
			$all->alias('build-all')
				->type(new KliTypeBool)
				->def(false)
				->description('To force rebuild ozone database classes.');

			// option: -n alias --namespace
			$rn = new KliOption('n');
			$rn->alias('namespace')
			   ->offsets(1)
			   ->type((new KliTypeString)->pattern('#^(?:[a-zA-Z][a-zA-Z0-9_]*(?:\\\\[a-zA-Z][a-zA-Z0-9_]*)*)$#', 'You should provide valid php namespace.'))
			   ->def(null)
			   ->description('The namespace of the table to be generated.');

			// option: -d alias --dir
			$rd = new KliOption('d');
			$rd->alias('dir')
			   ->offsets(2)
			   ->type((new KliTypePath)->dir()
									   ->writable())
			   ->def(null)
			   ->description('The rows classes directory.');

			// action: source database
			$source = new KliAction('source');
			$source->description('Run database query from source file.');

			// option: -d alias --dir
			$d = new KliOption('d');
			$d->alias('dir')
			  ->offsets(1)
			  ->type((new KliTypePath)->dir()
									  ->writable())
			  ->def('.')
			  ->description('The destination directory of the database file.');
			$f = new KliOption('f');
			$f->alias('file')
			  ->offsets(1)
			  ->type((new KliTypePath)->file())
			  ->description('The database source file to run.');

			$build->addOption($all);
			$refresh->addOption($rn, $rd);
			$generate->addOption($d);
			$backup->addOption($d);
			$source->addOption($f);

			$this->addAction($build, $refresh, $generate, $backup, $source);
		}
	}