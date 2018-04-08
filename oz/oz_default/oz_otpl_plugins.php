<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	defined('OZ_SELF_SECURITY_CHECK') or die;

	if (!function_exists("oz_file_link")) {
		function oz_file_link($file_id, $file_key = "", $file_quality = 0, $def = "")
		{
			$args  = func_get_args();
			$parts = explode("_", $args[0]);
			$url   = \OZONE\OZ\Core\SettingsManager::get("oz.files", "OZ_GET_FILE_URI_EXTRA_FORMAT");

			if (count($parts) === 2) { // @oz_file_link(file_id + '_' + file_key,file_quality,def)
				$def          = $file_quality;
				$file_quality = $file_key;
				$file_id      = $parts[0];
				$file_key     = $parts[1];
			}

			$file_quality = in_array($file_quality, [0, 1, 2, 3]) ? $file_quality : 0;

			if ($def && ($file_id === "0" || $file_key === "0")) {
				return $def;
			}

			return str_replace([
				"{oz_file_id}",
				"{oz_file_key}",
				"{oz_file_quality}"
			], [
				$file_id,
				$file_key,
				$file_quality
			], $url);
		}

		\OTpl::addPluginAlias("oz_file_link", "oz_file_link");
	}