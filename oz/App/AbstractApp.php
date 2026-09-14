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
use OZONE\Core\FS\Assets;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\FS\FS;
use OZONE\Core\Scopes\Interfaces\ScopeInterface;
use OZONE\Core\Scopes\StateLayout;
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

		// = Adds stateful settings source
		Settings::addSource(StateLayout::path($this, StateLayout::SETTINGS), true);

		// = Adds templates source
		Assets::addSource($this->getTemplatesDir()
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
		// Never auto-created: this is the volume a deployment mounts, and silently making an empty
		// one on a node whose disk did not come up hides the problem instead of reporting it.
		return $this->getProjectDir()
			->cd('data', false);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getPublicDir(): FilesManager
	{
		return $this->getDocumentRootDir();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getDocumentRootDir(): FilesManager
	{
		return $this->getProjectDir()
			->cd('public', true);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getStateSlug(): string
	{
		return ScopeInterface::ROOT_SCOPE;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getTempDir(): FilesManager
	{
		return StateLayout::dir($this, StateLayout::TEMP);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getStateStoreDir(): FilesManager
	{
		return StateLayout::dir($this, StateLayout::STATE);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getEnv(): Env
	{
		static $env = null;

		if (null === $env) {
			// The app's project directory, which an app may put elsewhere than OZ_PROJECT_DIR.
			$dir  = $this->getProjectDir();
			$path = \rtrim($dir->getRoot(), '/\\') . DS . '.env';

			if (!\is_file($path) || !\is_readable($path)) {
				// throws the error it always has
				$dir->filter()
					->isFile()
					->isReadable()
					->assert('.env');
			}

			$env = new Env($path);
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
		return $this->getSourcesDir()->cd('settings', true);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getStatefulSettingsDir(): FilesManager
	{
		return StateLayout::dir($this, StateLayout::SETTINGS);
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
		return StateLayout::dir($this, StateLayout::PRIVATE_FILES);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getPublicFilesDir(): FilesManager
	{
		return StateLayout::dir($this, StateLayout::PUBLIC_FILES);
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
		return $this->getProjectDir()
			->cd('.ozone/logs/', true);
	}
}
