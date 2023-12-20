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

use Gobl\DBAL\Exceptions\DBALException;
use OZONE\Core\App\Db;
use OZONE\Core\App\Keys;
use OZONE\Core\App\Settings;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\Plugins\Interfaces\PluginInterface;
use OZONE\Core\Scopes\AbstractScope;
use OZONE\Core\Utils\Hasher;

/**
 * Class AbstractPlugin.
 */
abstract class AbstractPlugin extends AbstractScope implements PluginInterface
{
	/**
	 * Plugin id, if empty it will be generated.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * AbstractPlugin constructor.
	 *
	 * @param string $namespace Plugin namespace
	 *
	 * @throws DBALException
	 */
	public function __construct(protected string $namespace)
	{
		if ($this->isEnabled() && $this->inPluginMode()) {
			$dir = $this->getPrivateDir()->cd('Db', true)->resolve('./');
			db()->ns($this->getDbNamespace())->enableORM($dir);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPrivateDir(): FilesManager
	{
		return app()->getPluginsDir()->cd($this->getID(), true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPublicDir(): FilesManager
	{
		return app()->getPublicDir()->cd('plugins' . DS . $this->getID(), true);
	}

	/**
	 * {@inheritDoc}
	 */
	final public function getID(): string
	{
		if (empty($this->id)) {
			$this->id = Hasher::shorten($this->getNamespace() . $this->getName() . Keys::salt());
		}

		return $this->id;
	}

	/**
	 * {@inheritDoc}
	 */
	final public function getNamespace(): string
	{
		return $this->namespace;
	}

	/**
	 * {@inheritDoc}
	 */
	final public function getDbNamespace(): string
	{
		return $this->namespace . '\\Db';
	}

	/**
	 * {@inheritDoc}
	 */
	final public function inPluginMode(): bool
	{
		return $this->getDbNamespace() !== Db::getProjectDbNamespace();
	}

	/**
	 * {@inheritDoc}
	 */
	final public function isEnabled(): bool
	{
		$plugins = Settings::load('oz.plugins');
		$class   = static::class;

		return isset($plugins[$class]) && $plugins[$class];
	}
}
