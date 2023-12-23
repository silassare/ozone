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

namespace OZONE\Core\Plugins;

use OZONE\Core\FS\FilesManager;
use OZONE\Core\Plugins\Interfaces\PluginInterface;
use OZONE\Core\Scopes\AbstractScope;
use OZONE\Core\Utils\Hasher;

/**
 * Class PluginScope.
 */
class PluginScope extends AbstractScope
{
	/**
	 * @var string
	 */
	protected string $id;

	/**
	 * PluginScope constructor.
	 *
	 * @param PluginInterface $plugin The plugin
	 */
	public function __construct(protected PluginInterface $plugin)
	{
		$this->id = $plugin->getName() . '-' . Hasher::shorten($plugin->getNamespace());
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName(): string
	{
		return $this->id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPrivateDir(): FilesManager
	{
		return app()->getPluginsDir()->cd($this->id, true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPublicDir(): FilesManager
	{
		return app()->getPublicDir()->cd('plugins' . DS . $this->id, true);
	}
}
