<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Cli\Cmd;

	use Gobl\ORM\Generators\Generator;
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
		 * @inheritdoc
		 *
		 * @throws \Exception
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
				case 'ts-bundle':
					$this->tsBundle($options);
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

			$cli       = $this->getCli();
			$all       = (bool)$options['a'];
			$structure = DbManager::getProjectDbDirectoryStructure();

			$db     = DbManager::getDb();
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

			$gen = new Generator($db, false, false);

			foreach ($map as $ns => $dir) {
				if ($all === false AND $ns === $structure['oz_db_namespace']) {
					continue;
				}
				$tables = $db->getTables($ns);
				// we (re)generate classes only for tables
				// in the given namespace
				$gen->generateORMClasses($tables, $dir);
			}

			try {
				$query = $db->buildDatabase();
				$db->executeMulti($query);
				$cli->writeLn('Success: database build done.');
			} catch (\Exception $e) {
				$cli->log($e)
					->writeLn('Error: database build fails. Open log file.');
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
			$gen    = new Generator($db, true, true);

			$gen->generateTSClasses($tables, $dir);
			$cli->writeLn("TypeScript entities bundle generated in: $dir");
		}

		/**
		 * Refresh database.
		 *
		 * You should backup your database first.
		 *
		 * @param array $options
		 *
		 * @throws \Kli\Exceptions\KliInputException
		 * @throws \OZONE\OZ\Exceptions\BaseException
		 * @throws \Exception
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

			$db    = DbManager::getDb();
			$found = $db->getTables($namespace);
			if (empty($found)) {
				throw new KliInputException(sprintf('There is no tables declared with "%s" namespace.', $namespace));
			}

			$query = $db->buildDatabase($namespace);
			// we (re)generate classes only for tables
			// in the given namespace
			$gen    = new Generator($db, false, false);
			$tables = $db->getTables($namespace);
			$gen->generateORMClasses($tables, $dir);

			try {
				$db->executeMulti($query);
				$cli->writeLn('Success: database refreshed.');
			} catch (\Exception $e) {
				$cli->log($e)
					->writeLn('Error: database refresh fails. Open log file.');
			}
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
			$query     = DbManager::getDb()
								  ->buildDatabase();
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
			$query = file_get_contents($f);
			$cli   = $this->getCli();

			if (empty($query)) {
				$cli->writeLn(sprintf('Error: the sql source file(%s) is empty.', $f));

				return;
			}

			$db = DbManager::getDb();

			try {
				$db->executeMulti($query);
				$cli->writeLn('Success: database updated.');
			} catch (\Exception $e) {
				$cli->log($e)
					->writeLn('Error: database update fails. Open log file.');
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

			$dir = $options['d'];
			$cli = $this->getCli();

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
		 * @inheritdoc
		 *
		 * @throws \Kli\Exceptions\KliException
		 */
		protected function describe()
		{
			$this->description("Manage your project database.");

			// action: build database
			$build = new KliAction('build');
			$build->description('To build the entire database and generate required classes.');

			// action: ts bundle
			$ts_bundle = new KliAction('ts-bundle');
			$ts_bundle->description('To generate entities classes for TypeScript.');

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
			   ->description('The db classes directory.');

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

			// option: -d alias --dir
			$b_d = new KliOption('d');
			$b_d->alias('dir')
				->offsets(1)
				->type((new KliTypePath)->dir()
										->writable())
				->def('.')
				->description('The destination directory of the bundle file.');

			$f = new KliOption('f');
			$f->alias('file')
			  ->offsets(1)
			  ->type((new KliTypePath)->file())
			  ->description('The database source file to run.');

			$build->addOption($all);
			$refresh->addOption($rn, $rd);
			$generate->addOption($d);
			$backup->addOption($d);
			$ts_bundle->addOption($b_d);
			$source->addOption($f);

			$this->addAction($build, $ts_bundle, $refresh, $generate, $backup, $source);
		}
	}