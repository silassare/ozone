<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Cli;

final class ProcessUtils
{
	private static $sig_child;

	private static $env;

	/**
	 * Checks for Windows environment.
	 *
	 * @return bool
	 */
	public static function isWindows()
	{
		return '\\' === \DIRECTORY_SEPARATOR;
	}

	/**
	 * Checks if PHP has been compiled with the '--enable-sigchild' option or not.
	 *
	 * @return bool
	 */
	public static function sigChildEnabled()
	{
		if (null !== self::$sig_child) {
			return self::$sig_child;
		}

		\ob_start();
		\phpinfo(\INFO_GENERAL);
		$info = \ob_get_clean();

		if (false !== \strpos($info, '--enable-sigchild')) {
			self::$sig_child = true;
		}

		return self::$sig_child = false;
	}

	/**
	 * Returns default env.
	 *
	 * @return array
	 */
	public static function getDefaultEnv()
	{
		if (!isset(self::$env)) {
			$env     = [];
			$sources = [$_SERVER, $_ENV];

			foreach ($sources as $source) {
				foreach ($source as $k => $v) {
					if (\is_string($v) && false !== $v = \getenv($k)) {
						$env[$k] = $v;
					}
				}
			}

			self::$env = $env;
		}

		return self::$env;
	}
}
