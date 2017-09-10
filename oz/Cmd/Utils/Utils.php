<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Cmd\Utils;

	final class Utils
	{
		public static function loadProjectConfig($folder)
		{
			$oz_config = $folder . DS . 'api' . DS . 'app' . DS . 'oz_settings' . DS . 'oz.config.php';

			if (file_exists($oz_config)) {
				$config = include $oz_config;

				if (is_array($config)) {
					return $config;
				}
			}

			return null;
		}
	}
