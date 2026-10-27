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
interface PluginInterface
{
	/**
	 * Returns the plugin scope.
	 */
	public function getScope(): ScopeInterface;

	/**
	 * Returns the plugin name.
	 */
	public function getName(): string;

	/**
	 * Returns the plugin install path.
	 *
	 * This is where the composer.json file is located.
	 */
	public function getInstallPath(): string;

	/**
	 * Returns the plugin package name.
	 */
	public function getPackageName(): string;

	/**
	 * Returns the plugin description.
	 */
	public function getDescription(): string;

	/**
	 * Returns the plugin author.
	 */
	public function getAuthor(): string;

	/**
	 * Returns the plugin version.
	 */
	public function getVersion(): string;

	/**
	 * Returns the plugin namespace.
	 */
	public function getNamespace(): string;

	/**
	 * Returns the plugin DB namespace.
	 */
	public function getDbNamespace(): string;

	/**
	 * Check if the plugin is enabled.
	 */
	public function isEnabled(): bool;

	/**
	 * Called on boot.
	 */
	public function boot(): void;

	/**
	 * Get the plugin instance.
	 */
	public static function instance(): static;
}
