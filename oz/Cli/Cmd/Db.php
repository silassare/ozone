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

	use Kli\KliAction;
	use Kli\KliOption;
	use Kli\Types\KliTypePath;
	use OZONE\OZ\Cli\OZoneCommand;
	use OZONE\OZ\Cli\Utils\Utils;
	use OZONE\OZ\Core\OZoneDb;
	use OZONE\OZ\FS\OZoneFS;

	final class Db extends OZoneCommand
	{
		/**
		 * {@inheritdoc}
		 */
		public function execute(KliAction $action, array $options, array $anonymous_options)
		{
			switch ($action->getName()) {
				case 'generate':
					$this->generate($options);
					break;
			}
		}

		/**
		 * generate database file.
		 *
		 * @param array $options
		 */
		private function generate(array $options)
		{
			Utils::assertProjectFolder();
			$folder = $options['o'];
			$query  = OZoneDb::getInstance()
							 ->getDb()
							 ->generateDataBaseQuery();

			$fs = new OZoneFS($folder);
			$fs->wf('database.sql', $query);

			$this->getCli()
				 ->writeLn(sprintf('Success: database file generated.'), true);
		}

		/**
		 * {@inheritdoc}
		 */
		protected function describe()
		{
			$this->description("Manage OZone project database.");

			// action: generate database query
			$generate = new KliAction('generate');
			$generate->description('Generate database file.');

			// option: -o alias --output-dir
			$o = new KliOption('o');
			$o->alias('output-dir')
			  ->offsets(1)
			  ->type((new KliTypePath)->dir()
									  ->writable())
			  ->def('.')
			  ->description('The folder in which the database file will be generated.');

			$generate->addOption($o);

			$this->addAction($generate);
		}
	}