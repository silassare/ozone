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
		Settings::addSource($this->getStatefulSettingsDir()
			->getRoot());
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
		return $this->getDataDir()->cd('settings', true);
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
		return $this->getDataDir()->cd('files', true);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getPublicFilesDir(): FilesManager
	{
		return $this->getPublicDir()->cd('static', true);
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
