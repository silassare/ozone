<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Cli\Utils;

	use OZONE\OZ\Core\DbManager;

	final class Utils
	{
		/**
		 * Load project config from a given project folder or current working dir.
		 *
		 * @param string|null $folder   the project folder
		 * @param bool        $required the config is required
		 *
		 * @return mixed
		 */
		public static function loadProjectConfig($folder = null, $required = false)
		{
			$folder    = empty($folder) ? getcwd() : $folder;
			$oz_config = $folder . DS . 'api' . DS . 'app' . DS . 'oz_settings' . DS . 'oz.config.php';

			if (file_exists($oz_config)) {
				$config = include $oz_config;

				if (self::isProjectConfigLike($config)) {
					return $config;
				}
			}

			if ($required) {
				$err = 'Error: there is no ozone project in "%s".' . PHP_EOL . 'Are you in project root folder?';
				throw new \RuntimeException(sprintf($err, $folder));
			}

			return null;
		}

		/**
		 * Checks for ozone config.
		 *
		 * @param mixed $config
		 *
		 * @return bool
		 */
		public static function isProjectConfigLike($config)
		{
			return is_array($config) AND isset($config['OZ_PROJECT_NAME']);
		}

		/**
		 * Asserts if a folder or current working directory contains OZone project.
		 *
		 * @param string|null $folder the project folder
		 */
		public static function assertProjectFolder($folder = null)
		{
			self::loadProjectConfig($folder, true);
		}

		/**
		 * Asserts if whether we have access to the database.
		 *
		 * @throws \OZONE\OZ\Exceptions\BaseException
		 */
		public static function assertDatabaseAccess()
		{
			self::assertProjectFolder();

			// we get connection to make sure that
			// we have access to the database
			// will throw error when something went wrong
			DbManager::getDb()
					 ->getConnection();
		}
	}
