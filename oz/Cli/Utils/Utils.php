<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Cli\Utils;

	use Kli\Exceptions\KliInputException;
	use OZONE\OZ\Core\DbManager;

	final class Utils
	{
		/**
		 * Loads project config from a given project folder or current working dir.
		 *
		 * @param string|null $folder   the project folder
		 * @param bool        $required the config is required
		 *
		 * @return mixed|null
		 * @throws \Kli\Exceptions\KliInputException when config is required and not found
		 */
		public static function loadProjectConfig($folder = null, $required = false)
		{
			$folder    = empty($folder) ? getcwd() : $folder;
			$oz_config = $folder . DS . 'api' . DS . 'app' . DS . 'oz_settings' . DS . 'oz.config.php';

			if (file_exists($oz_config)) {
				$config = include $oz_config;

				if (is_array($config)) {
					return $config;
				}
			}

			if ($required) {
				$err = 'Error: there is no ozone project in "%s". Are you in project root folder?';
				throw new KliInputException(sprintf($err, $folder));
			}

			return null;
		}

		/**
		 * Assert if a folder or current working directory contains OZone project.
		 *
		 * @param string|null $folder the project folder
		 *
		 * @throws \Kli\Exceptions\KliInputException
		 */
		public static function assertProjectFolder($folder = null)
		{
			self::loadProjectConfig($folder, true);
		}

		/**
		 * Assert if whether we have access to the database.
		 *
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public static function assertDatabaseAccess()
		{
			self::assertProjectFolder();

			// get connection to make sure that
			// we have access to the database
			// will throw error when something went wrong
			DbManager::getInstance()
				   ->getConnection();
		}
	}
