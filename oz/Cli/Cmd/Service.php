<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Cli\Cmd;

	use Gobl\DBAL\Table;
	use Gobl\ORM\Generators\Generator;
	use Kli\KliAction;
	use Kli\KliOption;
	use Kli\Types\KliTypeString;
	use OZONE\OZ\Cli\Command;
	use OZONE\OZ\Cli\Utils\Utils;
	use OZONE\OZ\Core\DbManager;
	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\FS\FilesManager;

	final class Service extends Command {
		/**
		 * {@inheritdoc}
		 *
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public function execute(KliAction $action, array $options, array $anonymous_options) {
			switch ($action->getName()) {
				case 'generate':
					$this->generate($options);
					break;
			}
		}

		/**
		 * Generate service for a table in the database.
		 *
		 * @param array $options
		 *
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \Exception
		 */
		private function generate(array $options) {
			Utils::assertDatabaseAccess();

			$table_name    = $options['t'];
			$service_name  = $options['n'];
			$service_class = $options['c'];
			$service       = SettingsManager::get('oz.services.list', $service_name);

			if (is_null($service)) {
				$db = DbManager::getInstance();
				$db->assertHasTable($table_name);
				$table       = $db->getTable($table_name);
				$fm          = new FilesManager(OZ_APP_DIR);
				$service_dir = $fm->cd('Services', true)
								  ->getRoot();

				$config            = SettingsManager::get('oz.config');
				$service_namespace = $config['OZ_PROJECT_NAMESPACE'] . '\\Services';
				$generator         = new Generator($db, false, false);
				$service           = $generator->generateOZServiceClass($table, $service_namespace, $service_dir, $service_name, $service_class);

				SettingsManager::setKey('oz.services.list', $service_name, $service);

				$this->getCli()
					 ->writeLn(sprintf('Success: service "%s" generated.', $service_name));
			} else {
				$this->getCli()
					 ->writeLn(sprintf('Error: Cannot overwrite service "%s" defined in "oz.services.list" settings.', $service_name));
			}
		}

		/**
		 * {@inheritdoc}
		 * @throws \Kli\Exceptions\KliException
		 */
		protected function describe() {
			$this->description("Manage your project service.");

			// action: generate service for a table
			$generate = new KliAction('generate');
			$generate->description('Generate service for a table in the database.');

			// option: -n alias --service-name
			$n = new KliOption('n');
			$n->alias('service-name')
			  ->type((new KliTypeString)->pattern('#^[a-zA-Z0-9_-]+$#', 'The service name is invalid.'))
			  ->required()
			  ->offsets(1)
			  ->prompt(true, 'The service name')
			  ->description('The service name.');

			// option: -t alias --table-name
			$t = new KliOption('t');
			$t->alias('table-name')
			  ->type((new KliTypeString)->pattern(Table::NAME_REG, 'The table name is invalid.'))
			  ->required()
			  ->offsets(2)
			  ->prompt(true, 'The table name')
			  ->description('The table name.');

			// option: -t alias --service-class
			$c = new KliOption('c');
			$c->alias('service-class')
			  ->type((new KliTypeString)->pattern('#^[a-zA-Z_][a-zA-Z0-9_]*$#', 'The service class name is invalid.'))
			  ->offsets(3)
			  ->def('')
			  ->prompt(true, 'The service class name')
			  ->description('The service class name.');

			$generate->addOption($n, $c, $t);

			$this->addAction($generate);
		}
	}