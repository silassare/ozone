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
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Plugins\Interfaces\PluginInterface;

/**
 * Class Plugins.
 */
class Plugins
{
	/**
	 * Returns a plugin instance.
	 *
	 * @param class-string<PluginInterface> $plugin
	 *
	 * @return PluginInterface
	 */
	public static function getPlugin(string $plugin): PluginInterface
	{
		/** @var array<class-string<PluginInterface>, PluginInterface> $plugins */
		static $plugins = [];

		if (!isset($plugins[$plugin])) {
			if (!\is_subclass_of($plugin, PluginInterface::class)) {
				throw new RuntimeException(
					\sprintf(
						'Plugin "%s" should implements "%s".',
						$plugin,
						PluginInterface::class
					)
				);
			}

			/* @var class-string<PluginInterface> $plugin */
			$plugins[$plugin] = $plugin::instance();
		}

		return $plugins[$plugin];
	}

	/**
	 * Boot plugins.
	 */
	public static function boot(): void
	{
		$plugins = Settings::load('oz.plugins');

		foreach ($plugins as $plugin => $enabled) {
			if ($enabled) {
				self::getPlugin($plugin)->boot();
			}
		}
	}

	/**
	 * Returns the plugin scope.
	 *
	 * @param PluginInterface $plugin
	 *
	 * @return PluginScope
	 */
	public static function scopeOf(PluginInterface $plugin): PluginScope
	{
		static $scopes = [];

		$class = \get_class($plugin);

		if (!isset($scopes[$class])) {
			$scopes[$class] = new PluginScope($plugin);
		}

		return $scopes[$class];
	}

	/**
	 * Returns the core ozone plugin.
	 *
	 * @return PluginInterface
	 */
	public static function ozone(): PluginInterface
	{
		return self::getPlugin(CorePlugin::class);
	}
}
