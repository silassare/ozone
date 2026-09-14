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
	public function getStateSlug(): string
	{
		return $this->getName();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getDataDir(): FilesManager
	{
		// One data root for the whole project: the kinds inside it are per scope, not the reverse.
		return app()->getDataDir();
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
	 *
	 * Kept as the name of the document root; {@see self::getPublicFilesDir()} is where public files
	 * are stored.
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
	public function getTemplatesDir(): FilesManager
	{
		return $this->getSourcesDir()->cd('templates', true);
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
	public function getLogsDir(): FilesManager
	{
		return app()->getProjectDir()
			->cd('.ozone/logs/', true);
	}
}
