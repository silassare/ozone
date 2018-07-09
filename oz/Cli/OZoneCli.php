<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Cli;

	use OZONE\OZ\Cli\Utils\Utils;
	use OZONE\OZ\Core\DbManager;
	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\Loader\ClassLoader;
	use Kli\Kli;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	include_once OZ_OZONE_DIR . 'oz_default' . DS . 'oz_config.php';
	include_once OZ_OZONE_DIR . 'oz_default' . DS . 'oz_define.php';
	include_once OZ_OZONE_DIR . 'Loader' . DS . 'ClassLoader.php';

	if (!OZ_OZONE_IS_CLI) {
		echo 'This is the command line tool for OZone Framework.';
		exit(1);
	}

	ClassLoader::addNamespace('\OZONE\OZ', OZ_OZONE_DIR);
	ClassLoader::addDir(OZ_OZONE_DIR . 'oz_vendors', true, 1);
	ClassLoader::addNamespace('\Kli', OZ_OZONE_DIR . 'oz_vendors' . DS . 'kli' . DS . 'src');
	ClassLoader::addNamespace('\Gobl', OZ_OZONE_DIR . 'oz_vendors' . DS . 'gobl' . DS . 'src');

	include_once OZ_OZONE_DIR . 'oz_default' . DS . 'oz_func.php';

	final class OZoneCli extends Kli
	{

		/**
		 * OZoneCli constructor.
		 */
		public function __construct()
		{
			parent::__construct('oz', true);
		}

		/**
		 * Run the commands.
		 *
		 * @param array $arg
		 *
		 * @throws \Kli\Exceptions\KliInputException
		 * @throws \Exception
		 */
		public function run(array $arg)
		{
			if ($config = Utils::loadProjectConfig()) {
				// Add project namespace root directory
				ClassLoader::addNamespace($config['OZ_PROJECT_NAMESPACE'], OZ_APP_DIR);
				// Init database
				DbManager::init();
			}

			$this->loadCommands();

			parent::execute($arg);
		}

		/**
		 * Loads all defined commands in oz.cli settings.
		 *
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \Exception
		 */
		private function loadCommands()
		{
			$list = SettingsManager::get('oz.cli');

			if (is_array($list) AND count($list)) {
				foreach ($list as $cmd_name => $cmd_class) {
					if (ClassLoader::exists($cmd_class)) {
						/** @var \OZONE\OZ\Cli\Command $cmd */
						$cmd = ClassLoader::instantiateClass($cmd_class, [$cmd_name, $this]);

						if ($cmd instanceof Command) {
							$this->addCommand($cmd);
						} else {
							throw new \Exception(sprintf('Your custom command class "%s" should extends "%s".', $cmd_class, Command::class));
						}
					} else {
						throw new \Exception(sprintf('Class "%s" not found for command "%s".', $cmd_class, $cmd_name));
					}
				}
			}
		}

		/**
		 * {@inheritdoc}
		 */
		public function welcome()
		{
			$this->write(file_get_contents(OZ_OZONE_DIR . 'welcome'), false);
		}

		/**
		 * {@inheritdoc}
		 */
		public function quit()
		{
			$this->writeLn('See you soon!');
			parent::quit();
		}
	}