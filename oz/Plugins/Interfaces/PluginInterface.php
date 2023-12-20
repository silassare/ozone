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

namespace OZONE\Core\Plugins\Interfaces;

use OZONE\Core\Scopes\Interfaces\ScopeInterface;

/**
 * Interface PluginInterface.
 */
interface PluginInterface extends ScopeInterface
{
	/**
	 * Returns the plugin ID.
	 *
	 * @return string
	 */
	public function getID(): string;

	/**
	 * Returns the plugin name.
	 *
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Returns the plugin description.
	 *
	 * @return string
	 */
	public function getDescription(): string;

	/**
	 * Returns the plugin author.
	 *
	 * @return string
	 */
	public function getAuthor(): string;

	/**
	 * Returns the plugin version.
	 *
	 * @return string
	 */
	public function getVersion(): string;

	/**
	 * Returns the plugin namespace.
	 *
	 * @return string
	 */
	public function getNamespace(): string;

	/**
	 * Returns the plugin DB namespace.
	 *
	 * @return string
	 */
	public function getDbNamespace(): string;

	/**
	 * Check if the plugin is enabled.
	 *
	 * @return bool
	 */
	public function isEnabled(): bool;

	/**
	 * Check if we are being used as a plugin.
	 *
	 * @return bool
	 */
	public function inPluginMode(): bool;

	/**
	 * Called on boot.
	 */
	public function boot(): void;

	/**
	 * Get the plugin instance.
	 */
	public static function instance(): self;
}
