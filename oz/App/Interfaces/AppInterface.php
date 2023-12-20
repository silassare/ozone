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
use OZONE\Core\Scopes\Interfaces\ScopeInterface;
use OZONE\Core\Utils\Env;
use Throwable;

/**
 * Interface AppInterface.
 */
interface AppInterface extends ScopeInterface
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
	 * Returns the environment instance.
	 */
	public function getEnv(): Env;

	/**
	 * Returns an instance of the files manager with the project directory as root.
	 *
	 * @return \OZONE\Core\FS\FilesManager
	 */
	public function getProjectDir(): FilesManager;

	/**
	 * Returns the project scope with the given name.
	 *
	 * @param string $scope the scope name
	 *
	 * @return ScopeInterface
	 */
	public function getScope(string $scope): ScopeInterface;

	/**
	 * Returns an instance of the files manager with the cache directory as root.
	 *
	 * @return \OZONE\Core\FS\FilesManager
	 */
	public function getCacheDir(): FilesManager;

	/**
	 * Returns an instance of the files manager with the plugins directory as root.
	 *
	 * @return \OZONE\Core\FS\FilesManager
	 */
	public function getPluginsDir(): FilesManager;

	/**
	 * Returns an instance of the files manager with the migrations directory as root.
	 *
	 * @return \OZONE\Core\FS\FilesManager
	 */
	public function getMigrationsDir(): FilesManager;
}
