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
	use Kli\Types\KliTypeString;
	use Kli\Types\KliTypePath;
	use OZONE\OZ\Cli\OZoneCommand;
	use OZONE\OZ\Cli\Utils\Utils;
	use OZONE\OZ\Core\OZoneSettings;
	use OZONE\OZ\FS\OZoneFS;
	use OZONE\OZ\FS\OZoneTemplates;
	use OZONE\OZ\Utils\OZoneStr;

	final class Project extends OZoneCommand
	{
		/**
		 * {@inheritdoc}
		 */
		public function execute(KliAction $action, array $options, array $anonymous_options)
		{
			switch ( $action->getName() ){
				case 'create':
					$this->create($options);
					break;
			}
		}

		/**
		 * create new project.
		 *
		 * @param array $options
		 */
		private function create(array $options)
		{
			$name       = $options['n'];
			$folder     = $options['f'];
			$class_name = $options['c'];
			$prefix     = strtoupper($options['p']);
			$config     = Utils::loadProjectConfig($folder);

			if ($config !== null) {
				$this->getCli()
					 ->writeLn(sprintf('Error: project "%s" created with "OZone %s" exists in "%s".', $config['OZ_PROJECT_NAME'], $config['OZ_OZONE_VERSION'], $folder), true);

				return;
			}

			$namespace      = sprintf('%s\\App', OZoneStr::removeSufix(strtoupper($class_name), 'APP'));
			$app_class_file = sprintf('%s.php', $class_name);

			$inject = OZoneSettings::genExportInfo('oz.config', [
				':oz:comment:1'        => 'REQUIRED: FOR OZONE USAGE =======================================',
				'OZ_OZONE_VERSION'     => OZ_OZONE_VERSION,
				'OZ_PROJECT_NAME'      => $name,
				'OZ_PROJECT_NAMESPACE' => $namespace,
				'OZ_PROJECT_CLASS'     => $class_name,
				'OZ_PROJECT_PREFIX'    => $prefix,
				'OZ_APP_DEBUG_MODE'    => 0,
				'OZ_APP_MAIN_URL'      => 'http://localhost',
				'OZ_APP_SID_NAME'      => 'OZONE_SID',
				'OZ_APP_APIKEY_NAME'   => 'ozone-apikey',
				':oz:comment:2'        => 'REQUIRED: DATABASE INFO =========================================',
				':oz:comment:3'        => 'we use and support MySQL RDBMS by default',
				'OZ_APP_DB_RDBMS'      => '\OZONE\OZ\Db\MySQL',
				'OZ_APP_DB_HOST'       => '__db_host__',
				'OZ_APP_DB_NAME'       => '__db_name__',
				'OZ_APP_DB_USER'       => '__db_user__',
				'OZ_APP_DB_PASS'       => '__db_pass__'
			]);

			$oz_config = OZoneTemplates::compute('oz:gen/settings.info.otpl', $inject);

			$inject = OZoneSettings::genExportInfo('oz.db', [
				'OZ_DB_TABLE_PREFIX' => OZoneStr::genRandomString(rand(3,6),'alpha')
			]);

			$oz_db = OZoneTemplates::compute('oz:gen/settings.info.otpl', $inject);

			$inject = OZoneSettings::genExportInfo('oz.keygen.salt', [
				':oz:comment:1'      => 'salt used to generate files tokens/keys.',
				'OZ_FKEY_GEN_SALT'   => OZoneStr::genRandomString(rand(32, 64)),
				':oz:comment:2'      => 'salt used to generate session identifiers.',
				'OZ_SID_GEN_SALT'    => OZoneStr::genRandomString(rand(32, 64)),
				':oz:comment:3'      => 'salt used to generate authentication tokens.',
				'OZ_AUTH_TOKEN_SALT' => OZoneStr::genRandomString(rand(32, 64)),
				':oz:comment:4'      => 'salt used to generate client id/apikey.',
				'OZ_CLID_GEN_SALT'   => OZoneStr::genRandomString(rand(32, 64))
			]);

			$oz_keygen_salt = OZoneTemplates::compute('oz:gen/settings.warn.otpl', $inject);

			$inject = [
				'oz_version_name'      => OZ_OZONE_VERSION_NAME,
				'oz_time'              => time(),
				'oz_project_namespace' => $namespace,
				'oz_project_class'     => $class_name
			];

			$app_class = OZoneTemplates::compute('oz:gen/sample.app.otpl', $inject);
			$api_index = OZoneTemplates::compute('oz:gen/index.api.otpl', $inject);

			$tpl_folder = OZ_OZONE_DIR . 'oz_templates' . DS;

			$fs = new OZoneFS($folder);
			$fs->cd('api', true)
					->cd('app', true)
						->cd('oz_settings', true)
							->wf('oz.config.php', $oz_config)
							->wf('oz.db.php', $oz_db)
							->wf('oz.keygen.salt.php', $oz_keygen_salt)
							->cd('..')
						->mkdir('oz_templates')
						->mkdir('oz_userfiles')
						->wf($app_class_file, $app_class)
						->cd('..')
					->ln(OZ_OZONE_DIR, 'oz')
					->wf('index.php', $api_index)
					->cp($tpl_folder . 'gen/robots.txt', 'robots.txt')
					->cp($tpl_folder . 'gen/favicon.ico', 'favicon.ico')
					->cp($tpl_folder . 'gen/htaccess.api.txt', '.htaccess')
					->cp($tpl_folder . 'gen/database.sql', 'database.sql')
					->cd('..');

			$this->getCli()
				 ->writeLn(sprintf('Success: project "%s" created in "%s".', $name, $folder), true);
		}

		/**
		 * {@inheritdoc}
		 */
		protected function describe()
		{
			$this->description("Manage your ozone project.");

			// action: project create
			$create = new KliAction('create');
			$create->description('Create new project.');

			// option: -f alias --folder
			$f = new KliOption('f');
			$f->alias('folder')
			  ->offsets(1)
			  ->type((new KliTypePath)->dir()
									  ->writable())
			  ->def('.')
			  ->prompt(true, 'The project folder')
			  ->description('The project folder path.');

			// option: -n alias --name
			$n = new KliOption('n');
			$n->alias('name')
			  ->offsets(2)
			  ->required()
			  ->type(new KliTypeString(1, 60))
			  ->prompt(true, 'Your new project name')
			  ->description('Your new project name.');

			// option: -c alias --class-name
			$c = new KliOption('c');
			$c->alias('class-name')
			  ->offsets(3)
			  ->required()
			// according to "http://php.net/manual/en/language.oop5.basic.php" visited on 1st Sept. 2017
			// php class name in regexp should be : ^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$
			  ->type((new KliTypeString(2, 30))->pattern('#^[a-zA-Z][a-zA-Z0-9_]+$#'))
			  ->def('SampleApp')
			  ->prompt(true, 'Your new project main class name to use')
			  ->description('Your new project main class name.');

			// option: -p alias --prefix
			$p = new KliOption('p');
			$p->alias('prefix')
			  ->offsets(4)
			  ->required()
			  ->type((new KliTypeString(2, 2))->pattern('#^[a-zA-Z][a-zA-Z0-9]$#', 'invalid project prefix.'))
			  ->def('SA')
			  ->prompt(true, 'Your new project prefix')
			  ->description('Your new project prefix.');

			$create->addOption($f)
				   ->addOption($n)
				   ->addOption($c)
				   ->addOption($p);

			$this->addAction($create);
		}
	}