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

use Gobl\DBAL\Db as GoblDb;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\SQLite\SQLite;
use Gobl\DBAL\Table;
use OZONE\Core\App\Settings;
use OZONE\Core\App\SettingsSchema;
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
	 * Boots the enabled plugins, each after the plugins it requires ({@see self::bootOrder()}).
	 */
	public static function boot(): void
	{
		foreach (self::bootOrder(Settings::load('oz.plugins')) as $plugin) {
			$plugin->boot();
		}
	}

	/**
	 * The enabled plugins of a list, in the order they boot: each after the plugins it requires.
	 *
	 * What a plugin requires is read from its package's Composer `require`: of the packages named
	 * there, those that are the packages of plugins in this list. Composer has already enforced
	 * their versions at install; what it cannot know is what is enabled, so a plugin whose required
	 * plugin is listed but disabled is refused here, by name, and so is a cycle, which Composer
	 * allows. Plugins with no requirement between them keep the order of the list.
	 *
	 * A disabled entry whose class is not installed is skipped, as it always was: it names nothing a
	 * plugin can require.
	 *
	 * @param array<class-string<PluginInterface>, bool> $listed the project's `oz.plugins`
	 *
	 * @return list<PluginInterface>
	 */
	public static function bootOrder(array $listed): array
	{
		/** @var array<string, class-string<PluginInterface>> $by_package */
		$by_package = [];

		foreach ($listed as $class => $enabled) {
			if (!$enabled && !\class_exists($class)) {
				continue;
			}

			$package = self::getPlugin($class)->getPackageName();

			if ('' !== $package) {
				$by_package[$package] = $class;
			}
		}

		$order = [];
		$done  = [];

		foreach ($listed as $class => $enabled) {
			if ($enabled) {
				self::visit($class, $listed, $by_package, $order, $done, []);
			}
		}

		return $order;
	}

	/**
	 * What enabling a plugin adds, read without booting it: who it is, the plugins it requires, the
	 * settings it ships (each group, with its keys: routes, commands, cron jobs, CRUD rules, texts)
	 * and its tables.
	 *
	 * The tables are its schema file's, run into a database of its own in memory, after OZone's and
	 * those of the plugins it requires (in boot order), since a table of it may reference theirs.
	 *
	 * @param array<class-string<PluginInterface>, bool> $listed the project's `oz.plugins`
	 *
	 * @return array{
	 *     class: class-string<PluginInterface>,
	 *     name: string,
	 *     package: string,
	 *     version: string,
	 *     description: string,
	 *     author: string,
	 *     enabled: bool,
	 *     requires: list<array{plugin: class-string<PluginInterface>, package: string, enabled: bool}>,
	 *     settings: array<string, list<string>>,
	 *     tables: list<string>,
	 * }
	 */
	public static function describe(PluginInterface $plugin, array $listed): array
	{
		$by_package = [];

		foreach ($listed as $class => $enabled) {
			if (!$enabled && !\class_exists($class)) {
				continue;
			}

			$by_package[self::getPlugin($class)->getPackageName()] = $class;
		}

		$requires = [];

		foreach ($plugin->getRequiredPackages() as $package) {
			$required = $by_package[$package] ?? null;

			if (null !== $required && $required !== $plugin::class) {
				$requires[] = ['plugin' => $required, 'package' => $package, 'enabled' => $listed[$required]];
			}
		}

		$source = $plugin->getSettingsSource();

		return [
			'class'       => $plugin::class,
			'name'        => $plugin->getName(),
			'package'     => $plugin->getPackageName(),
			'version'     => $plugin->getVersion(),
			'description' => $plugin->getDescription(),
			'author'      => $plugin->getAuthor(),
			'enabled'     => $listed[$plugin::class] ?? false,
			'requires'    => $requires,
			'settings'    => null === $source || !\is_dir($source) ? [] : SettingsSchema::keysIn($source),
			'tables'      => self::tablesOf($plugin, \array_column($requires, 'plugin')),
		];
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

	/**
	 * The tables a plugin's schema file declares, run into a database of its own in memory after
	 * OZone's and those of the given plugins.
	 *
	 * @param list<class-string<PluginInterface>> $required
	 *
	 * @return list<string>
	 */
	private static function tablesOf(PluginInterface $plugin, array $required): array
	{
		$file = $plugin->getSchemaFile();

		if (null === $file) {
			return [];
		}

		$db = GoblDb::newInstanceOf(SQLite::NAME, new DbConfig([
			'db_host'    => ':memory:',
			'db_port'    => '',
			'db_name'    => '',
			'db_user'    => '',
			'db_pass'    => '',
			'db_charset' => 'utf8',
			'db_collate' => 'utf8',
		]));

		// OZone's first and the plugin's last, each once: describing OZone itself loads its own.
		foreach (\array_unique([self::ozone()::class, ...$required, $plugin::class]) as $class) {
			$one    = self::getPlugin($class);
			$schema = $one->getSchemaFile();

			if (null !== $schema) {
				AbstractPlugin::schemaOf($schema)($db->ns($one->getDbNamespace()));
			}
		}

		return \array_values(\array_map(
			static fn (Table $table): string => $table->getName(),
			$db->getTables($plugin->getDbNamespace())
		));
	}

	/**
	 * Places a plugin in the boot order after the plugins it requires.
	 *
	 * `$path` holds the plugins being placed, from the first one asked for: what is in progress,
	 * registered on entry, so a plugin met again on its own path is a cycle and not a plugin to
	 * place twice. `$done` is what is placed, and only says so once it is.
	 *
	 * @param array<class-string<PluginInterface>, bool>   $listed
	 * @param array<string, class-string<PluginInterface>> $by_package
	 * @param list<PluginInterface>                        $order
	 * @param array<class-string<PluginInterface>, true>   $done
	 * @param list<class-string<PluginInterface>>          $path
	 */
	private static function visit(
		string $class,
		array $listed,
		array $by_package,
		array &$order,
		array &$done,
		array $path
	): void {
		if (isset($done[$class])) {
			return;
		}

		$at = \array_search($class, $path, true);

		if (false !== $at) {
			throw new RuntimeException(\sprintf(
				'Plugins require each other: %s.',
				\implode(' -> ', [...\array_slice($path, (int) $at), $class])
			));
		}

		$path[] = $class;
		$plugin = self::getPlugin($class);

		foreach ($plugin->getRequiredPackages() as $package) {
			$required = $by_package[$package] ?? null;

			if (null === $required || $required === $class) {
				continue;
			}

			if (!$listed[$required]) {
				throw new RuntimeException(\sprintf(
					'Plugin "%s" requires plugin "%s" (%s), which is disabled.',
					$class,
					$required,
					$package
				));
			}

			self::visit($required, $listed, $by_package, $order, $done, $path);
		}

		$done[$class] = true;
		$order[]      = $plugin;
	}
}
