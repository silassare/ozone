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
	 * The packages the plugin's own package requires through Composer, as its `require` names them.
	 *
	 * Those that are the packages of other listed plugins are the plugins it requires: they boot
	 * first, and one left disabled is refused. The version of each is Composer's to enforce.
	 *
	 * @return list<string>
	 */
	public function getRequiredPackages(): array;

	/**
	 * The directory of the settings the plugin ships, which its boot adds as a settings source; null
	 * for a plugin shipping none.
	 *
	 * Named rather than added from code so it can be read without booting the plugin: it is where its
	 * routes, commands, cron jobs, CRUD rules, seeders and texts are declared. Not the scope's
	 * settings directory, which holds the project's overrides of the plugin.
	 */
	public function getSettingsSource(): ?string;

	/**
	 * The file declaring the plugin's tables, or null for a plugin with none.
	 *
	 * It returns `function (NamespaceBuilder $ns): void`, the shape of OZone's own schema file, and
	 * the plugin's boot runs it for the plugin's namespace when a database collects its schema. A
	 * file and not a hook registered in code, so the tables can be listed without booting the plugin.
	 */
	public function getSchemaFile(): ?string;

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
