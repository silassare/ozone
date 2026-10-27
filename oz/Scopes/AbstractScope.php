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

namespace OZONE\Core\Scopes;

use Override;
use OZONE\Core\App\InstanceLayout;
use OZONE\Core\App\Settings;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\Scopes\Interfaces\ScopeInterface;

/**
 * Class AbstractScope.
 */
abstract class AbstractScope implements ScopeInterface
{
	/**
	 * AbstractScope constructor.
	 */
	protected function __construct()
	{
		// = Adds stateful settings source for this scope
		Settings::addSource(StateLayout::path($this, StateLayout::SETTINGS), true);
	}

	#[Override]
	public function getSettingsDir(): FilesManager
	{
		return $this->getSourcesDir()->cd('settings', true);
	}

	#[Override]
	public function getStatefulSettingsDir(): FilesManager
	{
		return StateLayout::dir($this, StateLayout::SETTINGS);
	}

	#[Override]
	public function getStateSlug(): string
	{
		return $this->getName();
	}

	#[Override]
	public function getDataDir(): FilesManager
	{
		// The application's root by default, so one volume holds the whole project's state. A scope
		// that must keep its state elsewhere -- another disk, another host's mount -- overrides this,
		// and StateLayout asks the scope and never app(), so the override is honoured everywhere.
		return app()->getDataDir();
	}

	#[Override]
	public function getTempDir(): FilesManager
	{
		return StateLayout::dir($this, StateLayout::TEMP);
	}

	#[Override]
	public function getStateStoreDir(): FilesManager
	{
		return StateLayout::dir($this, StateLayout::STATE);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Kept as the name of the document root; {@see self::getPublicFilesDir()} is where public files
	 * are stored.
	 */
	#[Override]
	public function getPublicDir(): FilesManager
	{
		return $this->getDocumentRootDir();
	}

	#[Override]
	public function getTemplatesDir(): FilesManager
	{
		return $this->getSourcesDir()->cd('templates', true);
	}

	#[Override]
	public function getPrivateFilesDir(): FilesManager
	{
		return StateLayout::dir($this, StateLayout::PRIVATE_FILES);
	}

	#[Override]
	public function getPublicFilesDir(): FilesManager
	{
		return StateLayout::dir($this, StateLayout::PUBLIC_FILES);
	}

	#[Override]
	public function getCacheDir(): FilesManager
	{
		return InstanceLayout::scopeCacheDir(app()->getProjectDir()->getRoot(), $this->getStateSlug());
	}

	#[Override]
	public function getLogsDir(): FilesManager
	{
		return InstanceLayout::logsDir(app()->getProjectDir()->getRoot());
	}
}
