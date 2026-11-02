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

			/** @var array<string, true> $building */
			static $building = [];

			// `instance()` is a project's code, and `Plugins::ozone()` is on the hot path of the
			// database set-up: a plugin whose `instance()` or `getName()` reaches back here found
			// the memo below still unwritten and built itself again, with nothing to stop it.
			if (isset($building[$plugin])) {
				throw new RuntimeException(\sprintf(
					'Plugin "%s" was asked for while it was being instantiated.',
					$plugin
				));
			}

			$building[$plugin] = true;

			/* @var class-string<PluginInterface> $plugin */
			try {
				$plugins[$plugin] = $plugin::instance();
			} finally {
				unset($building[$plugin]);
			}
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
			$enabled && self::getPlugin($plugin)->boot();
		}
	}

	/**
	 * Returns the plugin scope.
	 */
	public static function scopeOf(PluginInterface $plugin): PluginScope
	{
		/** @var array<class-string<PluginInterface>, PluginScope> $scopes */
		static $scopes = [];

		/** @var array<string, class-string<PluginInterface>> $claimed */
		static $claimed = [];

		$class = $plugin::class;

		/** @var array<string, true> $building */
		static $building = [];

		if (!isset($scopes[$class])) {
			// Building a plugin scope registers its settings source, which can reach code that asks
			// for the scope of the same plugin, while this memo still says it has none.
			if (isset($building[$class])) {
				throw new RuntimeException(\sprintf(
					'The scope of plugin "%s" was asked for while it was being built.',
					$class
				));
			}

			$building[$class] = true;

			try {
				$scope = new PluginScope($plugin);
				$slug  = $scope->getName();
			} finally {
				unset($building[$class]);
			}

			// Two plugins whose names slug the same way would share one state directory, one cache
			// directory and one public assets directory, and each would read the other's settings.
			if (isset($claimed[$slug]) && $claimed[$slug] !== $class) {
				throw new RuntimeException(\sprintf(
					'Plugins "%s" and "%s" both claim the state directory of "%s": their names slug'
						. ' the same way. Rename one of them.',
					$claimed[$slug],
					$class,
					$slug
				));
			}

			$claimed[$slug] = $class;
			$scopes[$class] = $scope;
		}

		return $scopes[$class];
	}

	/**
	 * Returns the core ozone plugin.
	 */
	public static function ozone(): PluginInterface
	{
		return self::getPlugin(CorePlugin::class);
	}
}
