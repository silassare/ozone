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

	use Kli\Exceptions\KliInputException;
	use Kli\KliAction;
	use Kli\KliOption;
	use Kli\Types\KliTypeString;
	use OZONE\OZ\Cli\Command;
	use OZONE\OZ\Cli\Utils\Utils;
	use OZONE\OZ\Core\Hasher;
	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\Db\OZClient;
	use OZONE\OZ\FS\FilesManager;
	use OZONE\OZ\FS\PathUtils;
	use OZONE\OZ\FS\TemplatesUtils;

	final class WebClient extends Command
	{
		/**
		 * {@inheritdoc}
		 *
		 * @throws \Exception
		 */
		public function execute(KliAction $action, array $options, array $anonymous_options)
		{
			switch ($action->getName()) {
				case 'add':
					$this->add($options);
					break;
			}
		}

		/**
		 * Adds new web client.
		 *
		 * @param array $options
		 *
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \Kli\Exceptions\KliInputException
		 * @throws \Exception
		 */
		private function add(array $options)
		{
			Utils::assertDatabaseAccess();

			$host           = $options['h'];
			$folder_name    = $options['f'];
			$about          = $options['a'];
			$project_folder = getcwd();
			$config         = Utils::loadProjectConfig($project_folder, true);

			$project_name = $config['OZ_PROJECT_NAME'];
			$namespace    = $config['OZ_PROJECT_NAMESPACE'];
			$class_name   = $config['OZ_PROJECT_CLASS'];
			$api_key      = Hasher::genClientId($host);

			if (!empty($folder_name)) {
				$abs_folder = PathUtils::resolve($project_folder, $folder_name);

				if (file_exists($abs_folder)) {
					if (is_file($abs_folder) OR !FilesManager::isEmptyDir($abs_folder)) {
						throw new KliInputException(sprintf('cannot overwrite "%s".', $abs_folder));
					}
				}

				$inject = SettingsManager::genExportInfo('oz.config', [
					'OZ_API_MAIN_URL' => $host
				]);

				$oz_config = TemplatesUtils::compute('oz:gen/settings.info.otpl', $inject);

				$inject = [
					'oz_version_name'      => OZ_OZONE_VERSION_NAME,
					'oz_time'              => time(),
					'oz_project_namespace' => $namespace,
					'oz_project_class'     => $class_name,
					'oz_default_api_key'   => $api_key
				];

				$www_index = TemplatesUtils::compute('oz:gen/index.www.otpl', $inject);

				$tpl_folder = OZ_OZONE_DIR . 'oz_templates' . DS;

				$fm = new FilesManager($project_folder);
				$fm->cd($abs_folder, true)
				   ->mkdir('assets')
				   ->cd('oz_private', true)
				   ->cd('oz_settings', true)
				   ->wf('oz.config.php', $oz_config)
				   ->cd('..')
				   ->mkdir('oz_templates')
				   ->cp($tpl_folder . 'gen/htaccess.deny.txt', '.htaccess')
				   ->cd('..')
				   ->wf('index.php', $www_index)
				   ->cp($tpl_folder . 'gen/robots.txt', 'robots.txt')
				   ->cp($tpl_folder . 'gen/favicon.ico', 'favicon.ico')
				   ->cp($tpl_folder . 'gen/htaccess.www.txt', '.htaccess');
			}

			$wc = new OZClient();
			$wc->setApiKey($api_key)
			   ->setAbout($about)
			   ->setUrl($host)
			   ->setAddTime(time())
			   ->setValid(true)
			   ->save();

			$this->getCli()
				 ->success(sprintf('web client added to project "%s".', $project_name))
				 ->info(sprintf('Client Host  : %s', $host))
				 ->info(sprintf('Client ApiKey: %s', $api_key));
		}

		/**
		 * {@inheritdoc}
		 * @throws \Kli\Exceptions\KliException
		 */
		protected function describe()
		{
			$this->description("Manage your ozone web client.");
			$host_reg = '#^https?\:\/\/(?:[a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])(?:\.(?:[a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9]))*$#';

			// action: add web client
			$add = new KliAction('add');
			$add->description('Add new web client to project.');

			// option: -h alias --host
			$h = new KliOption('h');
			$h->alias('host')
			  ->offsets(1)
			  ->required()
			  ->type((new KliTypeString)->pattern($host_reg, '"%s" is not a valid hostname.'))
			  ->prompt(true, 'The web client hostname')
			  ->description('The web client hostname.');

			// option: -f alias --folder
			$f = new KliOption('f');
			$f->alias('folder')
			  ->offsets(2)
			  ->type((new KliTypeString)->pattern('#^[^\\/?%*:|"<>]+$#'))
			  ->description('The web client folder name.');

			// option: -a alias --about
			$a = new KliOption('a');
			$a->alias('about')
			  ->offsets(3)
			  ->type(new KliTypeString)
			  ->required()
			  ->prompt(true, 'Short text about the web client')
			  ->description('Short text about the web client.');

			$add->addOption($h, $f, $a);

			$this->addAction($add);
		}
	}
