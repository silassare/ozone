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

use Override;
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
		parent::__construct();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getName(): string
	{
		return $this->scope_name;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getSourcesDir(): FilesManager
	{
		return app()->getPluginsSourcesDir()->cd($this->scope_psr4_ns_dir, true);
	}

	/**
	 * {@inheritDoc}
	 *
	 * A plugin's state lives under `data/{kind}/plugins/{plugin}`.
	 */
	#[Override]
	public function getStateSlug(): string
	{
		return 'plugins' . DS . $this->scope_name;
	}

	/**
	 * {@inheritDoc}
	 *
	 * A plugin has no document root of its own: it is served through the scope that loads it.
	 */
	#[Override]
	public function getDocumentRootDir(): FilesManager
	{
		return app()->getDocumentRootDir();
	}

	/**
	 * {@inheritDoc}
	 *
	 * Deliberately a subtree of the application's public files rather than its own state slug: one
	 * symlink then exposes a plugin's assets with the rest, at `/static/plugins/{plugin}/...`.
	 */
	#[Override]
	public function getPublicFilesDir(): FilesManager
	{
		return app()->getPublicFilesDir()->cd('plugins' . DS . $this->scope_name, true);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getCacheDir(): FilesManager
	{
		return app()->getProjectDir()
			->cd('.ozone/cache/plugins/' . $this->scope_name, true);
	}
}
