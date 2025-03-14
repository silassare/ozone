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

use OZONE\Core\FS\FilesManager;
use OZONE\Core\Scopes\AbstractScope;

/**
 * Class SubScope.
 */
final class SubScope extends AbstractScope
{
	/**
	 * SubScope constructor.
	 */
	public function __construct(protected string $name) {}

	/**
	 * {@inheritDoc}
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getSourcesDir(): FilesManager
	{
		return app()->getProjectDir()->cd('scopes' . DS . $this->name, true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDataDir(): FilesManager
	{
		return app()->getDataDir()->cd('scopes' . DS . $this->name, true);
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
	public function getCacheDir(): FilesManager
	{
		return app()->getProjectDir()
			->cd('.ozone/cache/scopes/' . $this->name, true);
	}
}
