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
use Override;
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
		PathUtils::registerResolver('oz', Templates::localize(...));

		// = Adds templates source
		Templates::addSource($this->getTemplatesDir()
			->getRoot());
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	final public function getName(): string
	{
		return ScopeInterface::ROOT_SCOPE;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function boot(): void {}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function onUnhandledThrowable(Throwable $t): void {}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function onUnhandledError(int $code, string $message, string $file, int $line): void {}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getScope(?string $scope = null): ScopeInterface
	{
		$scope ??= OZ_SCOPE_NAME;

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
	#[Override]
	public function getSourcesDir(): FilesManager
	{
		return FS::from(OZ_APP_DIR);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getDataDir(): FilesManager
	{
		return $this->getProjectDir()
			->cd('data', true);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getPublicDir(): FilesManager
	{
		return $this->getProjectDir()
			->cd('public/static', true);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
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
	#[Override]
	public function getProjectDir(): FilesManager
	{
		return FS::from(OZ_PROJECT_DIR);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getSettingsDir(): FilesManager
	{
		return $this->getSourcesDir()
			->cd('settings', true);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getTemplatesDir(): FilesManager
	{
		return $this->getSourcesDir()
			->cd('templates', true);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getPluginsSourcesDir(): FilesManager
	{
		return $this->getProjectDir()
			->cd('.ozone/plugins/', true);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getCacheDir(): FilesManager
	{
		return $this->getProjectDir()
			->cd('.ozone/cache/scopes/' . ScopeInterface::ROOT_SCOPE, true);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getPrivateFilesDir(): FilesManager
	{
		return $this->getDataDir()->cd('files', true);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getPublicFilesDir(): FilesManager
	{
		return $this->getPublicDir();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getMigrationsDir(): FilesManager
	{
		return $this->getSourcesDir()
			->cd('migrations', true);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getLogsDir(): FilesManager
	{
		return $this->getProjectDir();
	}
}
