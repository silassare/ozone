<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ;

	use OZONE\OZ\Core\OZoneSettings;
	use OZONE\OZ\Loader\ClassLoader;
	use Kli\Kli;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	include_once OZ_OZONE_DIR . 'oz_default' . DS . 'oz_config.php';
	include_once OZ_OZONE_DIR . 'oz_default' . DS . 'oz_define.php';
	include_once OZ_OZONE_DIR . 'Loader' . DS . 'ClassLoader.php';

	if (!OZ_OZONE_IS_CLI) {
		echo 'This is a command line tool for OZone Framework.';
		exit(1);
	}

	ClassLoader::addNamespace('\OZONE\OZ', OZ_OZONE_DIR);
	ClassLoader::addDir(OZ_OZONE_DIR . 'oz_vendors', true, 1);
	ClassLoader::addNamespace('\Kli', OZ_OZONE_DIR . 'oz_vendors' . DS . 'kli' . DS . 'src');

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
		 * run the cli.
		 *
		 * @param array $arg
		 */
		public function run(array $arg)
		{
			$this->welcome();
			$this->loadCommands();
			parent::execute($arg);
		}

		/**
		 * print a welcome message.
		 */
		private function welcome()
		{
			$this->write(file_get_contents(OZ_OZONE_DIR . 'welcome'));
		}

		/**
		 * loads all defined commands in oz.cli settings.
		 */
		private function loadCommands()
		{
			$list         = OZoneSettings::get('oz.cli');
			$oz_cmd_class = 'OZONE\OZ\Cmd\OZoneCommand';

			if (is_array($list) AND count($list)) {
				foreach ($list as $cmd_name => $cmd_class) {
					/** @var \OZONE\OZ\Cmd\OZoneCommand $cmd */
					$cmd = ClassLoader::instantiateClass($cmd_class, [$cmd_name, $this]);

					if (!is_subclass_of($cmd, $oz_cmd_class)) {
						throw new \Exception(sprintf('your custom command class "%s" should extends "%s".', $cmd_class, $oz_cmd_class));
					}

					$this->addCommand($cmd);
				}
			}
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