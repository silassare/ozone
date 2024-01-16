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
use PHPUtils\Str;

/**
 * Class PluginScope.
 */
class PluginScope extends AbstractScope
{
	/**
	 * The scope name.
	 *
	 * @var string
	 */
	protected string $scope_name;

	/**
	 * The scope psr4 namespace directory.
	 *
	 * This is the plugin namespace with backslash replaced by directory separator.
	 *
	 * @var string
	 */
	protected string $scope_psr4_ns_dir;

	/**
	 * PluginScope constructor.
	 *
	 * @param PluginInterface $plugin The plugin
	 */
	public function __construct(protected PluginInterface $plugin)
	{
		$namespace               = $plugin->getNamespace();
		$this->scope_psr4_ns_dir = \str_replace('\\', DS, $namespace);
		$this->scope_name        = Str::stringToURLSlug($this->plugin->getName());
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName(): string
	{
		return $this->scope_name;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPrivateDir(): FilesManager
	{
		return app()->getPluginsDir()->cd($this->scope_psr4_ns_dir, true);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPublicDir(): FilesManager
	{
		return app()->getPublicDir()->cd('plugins' . DS . $this->scope_name, true);
	}
}
