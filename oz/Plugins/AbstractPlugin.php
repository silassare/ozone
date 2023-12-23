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

use OZONE\Core\App\Settings;
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
	 * @param string $install_path Plugin install path
	 */
	public function __construct(
		protected string $name,
		protected string $namespace,
		protected string $install_path
	) {
		$this->composer_json = new ComposerJSON($this->install_path . DS . 'composer.json');
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
		return $this->namespace . '\\Db';
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
