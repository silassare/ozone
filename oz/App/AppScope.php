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

use OZONE\Core\App\Interfaces\AppScopeInterface;
use OZONE\Core\FS\FilesManager;

/**
 * Class AppScope.
 */
class AppScope implements AppScopeInterface
{
	/**
	 * AppScope constructor.
	 */
	public function __construct(protected string $name) {}

	/**
	 * {@inheritDoc}
	 */
	final public function getName(): string
	{
		return $this->name;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPrivateDir(): FilesManager
	{
		return app()->getProjectDir()->cd('scopes' . DS . $this->name, true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPublicDir(): FilesManager
	{
		return app()->getProjectDir()->cd('public' . DS . $this->name, true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSettingsDir(): FilesManager
	{
		return $this->getPrivateDir()->cd('settings', true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTemplatesDir(): FilesManager
	{
		return $this->getPrivateDir()->cd('templates', true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPrivateFilesDir(): FilesManager
	{
		return $this->getPrivateDir()->cd('files', true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPublicFilesDir(): FilesManager
	{
		return $this->getPublicDir()->cd('static', true);
	}
}
