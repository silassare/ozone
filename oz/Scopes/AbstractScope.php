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

use OZONE\Core\FS\FilesManager;
use OZONE\Core\Scopes\Interfaces\ScopeInterface;

/**
 * Class AbstractScope.
 */
abstract class AbstractScope implements ScopeInterface
{
	/**
	 * {@inheritDoc}
	 */
	public function getSettingsDir(): FilesManager
	{
		return $this->getSourcesDir()->cd('settings', true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTemplatesDir(): FilesManager
	{
		return $this->getSourcesDir()->cd('templates', true);
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
		return $this->getPublicDir()->cd('static', true);
	}
}
