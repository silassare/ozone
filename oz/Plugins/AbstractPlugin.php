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

use OZONE\Core\App\Db;
use OZONE\Core\App\Settings;
use OZONE\Core\FS\FS;
use OZONE\Core\Hooks\Events\DbReadyHook;
use OZONE\Core\Loader\ClassLoader;
use OZONE\Core\Plugins\Interfaces\PluginInterface;
use OZONE\Core\Utils\ComposerJSON;

/**
 * Class AbstractPlugin.
 */
abstract class AbstractPlugin implements PluginInterface
{
	private ComposerJSON $composer_json;

	/**
	 * AbstractPlugin constructor.
	 *
	 * @param string $namespace    Plugin namespace
	 * @param string $install_path Plugin install path this is where the composer.json file is located
	 */
	public function __construct(
		protected string $name,
		protected string $namespace,
		protected string $install_path
	) {
		$fs = FS::fromRoot();

		// this wil fail if the install path is not a directory
		$fs->cd($this->install_path);

		$this->install_path = $fs->resolve('.');

		$composer_file = './composer.json';

		$fs->filter()->isFile()->isReadable()->assert($composer_file);

		$this->composer_json = new ComposerJSON($fs->resolve($composer_file));
	}

	/**
	 * {@inheritDoc}
	 */
	public function getScope(): PluginScope
	{
		return Plugins::scopeOf($this);
	}

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
	public function getInstallPath(): string
	{
		return $this->install_path;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPackageName(): string
	{
		return $this->composer_json->get('name', '');
	}

	/**
	 * {@inheritDoc}
	 */
	public function getDescription(): string
	{
		return $this->composer_json->get('description', '');
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAuthor(): string
	{
		return $this->composer_json->get('authors.0.name', '');
	}

	/**
	 * {@inheritDoc}
	 */
	public function getVersion(): string
	{
		return $this->composer_json->get('version', '0.0.0');
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
		return $this->namespace . '\Db';
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

	/**
	 * {@inheritDoc}
	 */
	public function boot(): void
	{
		if ($this->isEnabled()) {
			// add directory to the plugin namespace
			ClassLoader::addNamespace($this->namespace, $this->getScope()->getSourcesDir()->getRoot());

			// enable ORM for the plugin if applicable
			if ($this->shouldEnableORM()) {
				DbReadyHook::listen(function (DbReadyHook $hook) {
					$hook->db->ns($this->getDbNamespace())->enableORM(Db::dir($this->getScope())->getRoot());
				});
			}
		}
	}

	/**
	 * Check if the plugin ORM should be enabled.
	 *
	 * @return bool
	 */
	protected function shouldEnableORM(): bool
	{
		// for core plugin it's already handled
		if ($this instanceof CorePlugin) {
			return false;
		}

		// if we are in plugin development project it's already handled
		$plugin_fm  = FS::from($this->getInstallPath());
		$project_fm = FS::from(OZ_PROJECT_DIR);

		return !($plugin_fm->resolve('./') === $project_fm->resolve('./'));
	}
}
