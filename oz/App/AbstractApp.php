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
use OZONE\Core\FS\FS;
use OZONE\Core\FS\Templates;
use OZONE\Core\Scopes\Interfaces\ScopeInterface;
use OZONE\Core\Utils\Env;
use PHPUtils\FS\PathUtils;
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

		// = Adds register oz:// protocol resolver
		PathUtils::registerResolver('oz', static fn (string $path) => Templates::localize($path));

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
	public function getScope(?string $scope = null): ScopeInterface
	{
		$scope = $scope ?? OZ_SCOPE_NAME;

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

			$scopes[$scope] = new SubScope($scope);
		}

		return $scopes[$scope];
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSourcesDir(): FilesManager
	{
		return FS::from(OZ_APP_DIR);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDataDir(): FilesManager
	{
		return $this->getProjectDir()
			->cd('data', true);
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
			$dir = $this->getProjectDir();

			$dir->filter()
				->isFile()
				->isReadable()
				->assert('.env');

			$env = new Env($dir->resolve('.env'));
		}

		return $env;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getProjectDir(): FilesManager
	{
		return FS::from(OZ_PROJECT_DIR);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSettingsDir(): FilesManager
	{
		return $this->getSourcesDir()
			->cd('settings', true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTemplatesDir(): FilesManager
	{
		return $this->getSourcesDir()
			->cd('templates', true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPluginsSourcesDir(): FilesManager
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
			->cd('.ozone/cache/scopes/' . ScopeInterface::ROOT_SCOPE, true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPrivateFilesDir(): FilesManager
	{
		return $this->getDataDir()->cd('files', true);
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
		return $this->getSourcesDir()
			->cd('migrations', true);
	}
}
