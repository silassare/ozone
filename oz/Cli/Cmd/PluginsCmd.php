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

namespace OZONE\Core\Cli\Cmd;

use Kli\Exceptions\KliInputException;
use Kli\KliArgs;
use Kli\Table\KliTable;
use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Utils\Utils;
use OZONE\Core\Plugins\Interfaces\PluginInterface;
use OZONE\Core\Plugins\Plugins;

/**
 * Class PluginsCmd.
 *
 * What the plugins of a project are, in the order they boot, and what enabling one adds -- read
 * from what each ships, without booting it, which is what a tool asks before installing one.
 */
final class PluginsCmd extends Command
{
	/**
	 * Lists the plugins the project lists, enabled ones in the order they boot, then the others.
	 */
	public function list(KliArgs $args): void
	{
		Utils::assertProjectLoaded();

		/** @var array<class-string<PluginInterface>, bool> $listed */
		$listed = Settings::load('oz.plugins');
		$booted = \array_map(
			static fn (PluginInterface $plugin): string => $plugin::class,
			Plugins::bootOrder($listed)
		);
		$rows   = [];

		foreach ([...$booted, ...\array_keys(\array_filter($listed, static fn (bool $on): bool => !$on))] as $class) {
			if (!\class_exists($class)) {
				$rows[] = ['class' => $class, 'package' => '', 'version' => '', 'enabled' => false, 'requires' => []];

				continue;
			}

			$described = Plugins::describe(Plugins::getPlugin($class), $listed);
			$rows[]    = [
				'class'    => $class,
				'package'  => $described['package'],
				'version'  => $described['version'],
				'enabled'  => $described['enabled'],
				'requires' => \array_column($described['requires'], 'plugin'),
			];
		}

		$cli = $this->getCli();

		if ($args->get('json')) {
			$cli->writeJson(['plugins' => $rows]);
		}

		$table = new KliTable();

		$table->addHeader('Plugin', 'class')->alignLeft();
		$table->addHeader('Package', 'package')->alignLeft();
		$table->addHeader('Version', 'version')->alignLeft();
		$table->addHeader('Enabled', 'enabled')->alignLeft();
		$table->addHeader('Requires', 'requires')->alignLeft();
		$table->addRows(\array_map(static fn (array $row): array => [
			'class'    => $row['class'],
			'package'  => $row['package'],
			'version'  => $row['version'],
			'enabled'  => $row['enabled'] ? 'yes' : 'no',
			'requires' => \implode(', ', $row['requires']),
		], $rows));

		$cli->writeLn((string) $table, false);
	}

	/**
	 * Says what enabling a plugin adds: who it is, what it requires, its settings and its tables.
	 */
	public function describePlugin(KliArgs $args): void
	{
		Utils::assertProjectLoaded();

		/** @var array<class-string<PluginInterface>, bool> $listed */
		$listed    = Settings::load('oz.plugins');
		$described = Plugins::describe(self::pluginOf((string) $args->get('plugin'), $listed), $listed);
		$cli       = $this->getCli();

		if ($args->get('json')) {
			$cli->writeJson($described);
		}

		$cli->info(\sprintf('%s %s (%s)', $described['package'], $described['version'], $described['class']));
		$cli->writeLn($described['enabled'] ? 'Enabled.' : 'Not enabled.');

		foreach ($described['requires'] as $required) {
			$cli->writeLn(\sprintf(
				'Requires %s (%s)%s',
				$required['plugin'],
				$required['package'],
				$required['enabled'] ? '' : ', which is disabled'
			));
		}

		foreach ($described['settings'] as $group => $keys) {
			$cli->writeLn(\sprintf('Settings %s: %s', $group, \implode(', ', $keys)));
		}

		$tables = [] === $described['tables'] ? 'none' : \implode(', ', $described['tables']);

		$cli->writeLn(\sprintf('Tables: %s', $tables));
	}

	#[Override]
	protected function describe(): void
	{
		$this->description('Inspect the plugins of the project.');

		$list = $this->action('list', 'List the plugins, enabled ones in the order they boot.');
		self::withJsonSupport($list);
		$list->handler($this->list(...));

		$describe = $this->action('describe', 'Say what enabling a plugin adds, without booting it.');
		$describe->option('plugin', 'p', [], 1)
			->required()
			->description('The plugin, by its class or its Composer package name.')
			->string();
		self::withJsonSupport($describe);
		$describe->handler($this->describePlugin(...));
	}

	/**
	 * The listed plugin a class or a package name names.
	 *
	 * @param array<class-string<PluginInterface>, bool> $listed
	 */
	private static function pluginOf(string $name, array $listed): PluginInterface
	{
		foreach ($listed as $class => $_) {
			if (!\class_exists($class)) {
				continue;
			}

			$plugin = Plugins::getPlugin($class);

			if ($class === $name || $plugin->getPackageName() === $name) {
				return $plugin;
			}
		}

		throw new KliInputException(\sprintf(
			'No plugin "%s" in oz.plugins: name one by its class or its package.',
			$name
		));
	}
}
