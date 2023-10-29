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

use Gobl\DBAL\Interfaces\RDBMSInterface;
use OZONE\Core\App\Db;
use OZONE\Core\App\Interfaces\AppInterface;
use OZONE\Core\Logger\Logger;
use OZONE\Core\OZone;
use PHPUtils\Env\EnvParser;

if (!\function_exists('oz_logger')) {
	/**
	 * Write to log file.
	 *
	 * @param mixed $value
	 */
	function oz_logger(mixed $value): void
	{
		Logger::log($value);
	}

	/**
	 * Alias for {@see OZone::app()}.
	 *
	 * @return \OZONE\Core\App\Interfaces\AppInterface
	 */
	function app(): AppInterface
	{
		return OZone::app();
	}

	/**
	 * Get the value of an environment variable.
	 *
	 * @param string                     $key
	 * @param null|bool|float|int|string $default
	 *
	 * @return null|bool|float|int|string
	 */
	function env(string $key, mixed $default = null): null|bool|float|int|string
	{
		static $env = null;

		if (null === $env) {
			$env   = new EnvParser('');
			$files = app()->getEnvFiles();

			foreach ($files as $file) {
				if (\is_file($file)) {
					$env->mergeFromFile($file);
				}
			}
		}

		return $env->getEnv($key, $default);
	}

	/**
	 * Alias for {@see Db::get()}.
	 *
	 * @return \Gobl\DBAL\Interfaces\RDBMSInterface
	 */
	function db(): RDBMSInterface
	{
		return Db::get();
	}

	Logger::registerHandlers();
}
