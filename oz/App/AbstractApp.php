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

namespace OZONE\Core\App;

use OZONE\Core\App\Interfaces\AppInterface;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\FS\Templates;
use OZONE\Core\Utils\Env;
use Throwable;

/**
 * Class AbstractApp.
 */
abstract class AbstractApp implements AppInterface
{
	/**
	 * AbstractApp constructor.
	 */
	public function __construct()
	{
		// = Adds settings source
		Settings::addSource($this->getSettingsDir()
			->getRoot());

		// = Adds templates source
		Templates::addSource($this->getTemplatesDir()
			->getRoot());
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot(): void {}

	/**
	 * {@inheritDoc}
	 */
	public function onUnhandledThrowable(Throwable $t): void {}

	/**
	 * {@inheritDoc}
	 */
	public function onUnhandledError(int $code, string $message, string $file, int $line): void {}

	/**
	 * {@inheritDoc}
	 */
	public function getEnv(): Env
	{
		static $env = null;

		if (null === $env) {
			$env = new Env($this->getProjectDir()->resolve('.env'));
		}

		return $env;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getProjectDir(): FilesManager
	{
		return new FilesManager(OZ_PROJECT_DIR);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAppDir(): FilesManager
	{
		return new FilesManager(OZ_APP_DIR);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSettingsDir(): FilesManager
	{
		return $this->getAppDir()
			->cd('oz_settings', true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTemplatesDir(): FilesManager
	{
		return $this->getAppDir()
			->cd('oz_templates', true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getCacheDir(): FilesManager
	{
		return $this->getAppDir()
			->cd('oz_cache', true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPrivateFilesDir(): FilesManager
	{
		return $this->getAppDir()
			->cd('oz_files', true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPublicFilesDir(): FilesManager
	{
		return $this->getAppDir()
			->cd('../public/static', true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getMigrationsDir(): FilesManager
	{
		return $this->getAppDir()
			->cd('oz_migrations', true);
	}
}
