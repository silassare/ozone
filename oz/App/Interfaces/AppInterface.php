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

namespace OZONE\Core\App\Interfaces;

use OZONE\Core\FS\FilesManager;
use Throwable;

/**
 * Interface AppInterface.
 */
interface AppInterface
{
	/**
	 * Called when ozone is booting.
	 *
	 * You should register your plugins here.
	 */
	public function boot(): void;

	/**
	 * Unhandled throwable hook. Is called when an unhandled throwable occurs.
	 *
	 * @param Throwable $t the throwable (exception/error)
	 */
	public function onUnhandledThrowable(Throwable $t);

	/**
	 * Unhandled error hook. Is called when an unhandled error occurs.
	 *
	 * @param int    $code    the error code
	 * @param string $message the error message
	 * @param string $file    the file where it occurs
	 * @param int    $line    the file line where it occurs
	 */
	public function onUnhandledError(int $code, string $message, string $file, int $line);

	/**
	 * Returns environment variables files.
	 *
	 * @return string[]
	 */
	public function getEnvFiles(): array;

	/**
	 * Returns the app directory.
	 *
	 * @return \OZONE\Core\FS\FilesManager
	 */
	public function getAppDir(): FilesManager;

	/**
	 * Returns the settings directory.
	 *
	 * @return \OZONE\Core\FS\FilesManager
	 */
	public function getSettingsDir(): FilesManager;

	/**
	 * Returns the templates directory.
	 *
	 * @return \OZONE\Core\FS\FilesManager
	 */
	public function getTemplatesDir(): FilesManager;

	/**
	 * Returns the cache directory.
	 *
	 * @return \OZONE\Core\FS\FilesManager
	 */
	public function getCacheDir(): FilesManager;

	/**
	 * Returns the private files directory.
	 *
	 * This directory should be protected from public access.
	 * This is where you should store your private files.
	 *
	 * @return \OZONE\Core\FS\FilesManager
	 */
	public function getPrivateFilesDir(): FilesManager;

	/**
	 * Returns public directory.
	 *
	 * This directory is accessible from the web.
	 * This is where public files should be stored.
	 *
	 * @return \OZONE\Core\FS\FilesManager
	 */
	public function getPublicFilesDir(): FilesManager;

	/**
	 * Returns the migrations directory.
	 *
	 * @return \OZONE\Core\FS\FilesManager
	 */
	public function getMigrationsDir(): FilesManager;
}
