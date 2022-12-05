<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace OZONE\OZ\Ofv;

use Exception;
use OZONE\OZ\Exceptions\RuntimeException;

/**
 * Class OFormUtils.
 *
 * @deprecated
 */
final class OFormUtils
{
	/**
	 * Loads form validators in a given directory.
	 *
	 * @deprecated
	 *
	 * @param string $dir         the directory path
	 * @param bool   $silent_mode whether to throw exception when directory was not found
	 *
	 * @throws Exception when we couldn't access the directory and $silent_mode is set to false
	 */
	public static function loadValidators(string $dir, bool $silent_mode = false): void
	{
		if (\DIRECTORY_SEPARATOR === '\\') {
			$dir = \str_replace('/', '\\', $dir);
		}

		$ofv_validators_func_reg = '#^ofv\.[a-z0-9_]+\.php$#';

		if (\file_exists($dir) && \is_dir($dir) && $res = @\opendir($dir)) {
			while (false !== ($filename = \readdir($res))) {
				if ('.' !== $filename && '..' !== $filename) {
					$c_path = $dir . \DIRECTORY_SEPARATOR . $filename;

					if (\is_file($c_path) && \preg_match($ofv_validators_func_reg, $filename)) {
						require_once $c_path;
					}
				}
			}

			\closedir($res);
		} elseif (!$silent_mode) {
			throw new RuntimeException("OFormUtils: {$dir} not found or is not a valid directory.");
		}
	}
}
