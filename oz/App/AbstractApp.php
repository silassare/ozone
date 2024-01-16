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

use InvalidArgumentException;
use OZONE\Core\App\Interfaces\AppInterface;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\FS\Templates;
use OZONE\Core\Scopes\Interfaces\ScopeInterface;
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
	final public function getName(): string
	{
		return ScopeInterface::ROOT_SCOPE;
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
	public function getScope(string $scope): ScopeInterface
	{
		if (ScopeInterface::ROOT_SCOPE === $scope) {
			return $this;
		}

		/** @var array<string, ScopeInterface> $scopes */
		static $scopes = [];

		if (!isset($scopes[$scope])) {
			try {
				$this->getProjectDir()
					->filter()
					->isDir()
					->assert('scopes' . DS . $scope);
			} catch (Throwable $t) {
				throw new InvalidArgumentException(\sprintf('Scope "%s" not found.', $scope), 0, $t);
			}

			$scopes[$scope] = new AppScope($scope);
		}

		return $scopes[$scope];
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPrivateDir(): FilesManager
	{
		return new FilesManager(OZ_APP_DIR);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPublicDir(): FilesManager
	{
		return $this->getProjectDir()
			->cd('public/static', true);
	}

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
	public function getSettingsDir(): FilesManager
	{
		return $this->getPrivateDir()
			->cd('settings', true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTemplatesDir(): FilesManager
	{
		return $this->getPrivateDir()
			->cd('templates', true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPluginsDir(): FilesManager
	{
		return $this->getProjectDir()
			->cd('.ozone/plugins/', true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getCacheDir(): FilesManager
	{
		return $this->getProjectDir()
			->cd('.ozone/cache/', true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPrivateFilesDir(): FilesManager
	{
		return $this->getPrivateDir()
			->cd('files', true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPublicFilesDir(): FilesManager
	{
		return $this->getPublicDir();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getMigrationsDir(): FilesManager
	{
		return $this->getPrivateDir()
			->cd('migrations', true);
	}
}
