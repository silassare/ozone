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

use Kli\KliAction;
use Kli\KliArgs;
use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\App\SettingsSchema;
use OZONE\Core\Cli\Command;

/**
 * Class SettingsCmd.
 *
 * A settings key is documented where it is declared, so the schema this command exports is read from
 * the declarations themselves ({@see SettingsSchema}) and cannot fall behind them.
 */
final class SettingsCmd extends Command
{
	/**
	 * Sets a new setting in the project.
	 */
	public function set(
		KliArgs $args
	): void {
		$scope_name      = $args->get('scope');
		$group           = (string) $args->get('group');
		$key             = (string) $args->get('key');
		$value           = self::parseValue((string) $args->get('value'));
		$source          = (bool) $args->get('source');

		Settings::set($group, $key, $value, scope($scope_name), !$source);

		$cli   = $this->getCli();
		$style = $cli->style()->green();

		$cli->success(\sprintf(
			'Setting %s in group %s has been successfully set.',
			$style->apply($key),
			$style->apply($group)
		));
	}

	/**
	 * Unsets a setting in the project.
	 */
	public function unset(
		KliArgs $args
	): void {
		$scope_name      = $args->get('scope');
		$group           = (string) $args->get('group');
		$key             = (string) $args->get('key');
		$source          = (bool) $args->get('source');

		Settings::unset($group, $key, scope($scope_name), !$source);

		$cli   = $this->getCli();
		$style = $cli->style()->green();

		$cli->success(\sprintf(
			'Setting %s in group %s has been successfully unset.',
			$style->apply($key),
			$style->apply($group)
		));
	}

	/**
	 * Exports the settings as declarations: every key with its type, default and documentation.
	 *
	 * `--client` answers the keys a browser may read instead, with the values this project answers,
	 * which is what a front end reads so it follows an override rather than holding a copy of a
	 * default.
	 */
	public function export(KliArgs $args): void
	{
		$cli = $this->getCli();

		if ($args->get('client')) {
			$this->exportForClient($args);

			return;
		}

		$schema = SettingsSchema::describe();
		$failed = [] !== $schema['errors'];

		if ($args->get('json')) {
			$cli->writeJson($schema, !$failed, $failed ? 1 : 0, $failed ? 'OZ_SETTINGS_UNREADABLE' : '');
		}

		foreach ($schema['errors'] as $error) {
			$cli->warn(\sprintf('%s: %s', $error['group'], $error['reason']));
		}

		$counts = [];

		foreach ($schema['settings'] as $setting) {
			$counts[$setting['group']] ??= ['keys' => 0, 'doc' => 0, 'client' => 0];
			++$counts[$setting['group']]['keys'];
			$counts[$setting['group']]['doc'] += '' === $setting['doc'] ? 0 : 1;
			$counts[$setting['group']]['client'] += $setting['client'] ? 1 : 0;
		}

		foreach ($counts as $group => $count) {
			$cli->writeLn(\sprintf(
				'%-28s %4d keys, %4d documented, %2d for the client',
				$group,
				$count['keys'],
				$count['doc'],
				$count['client']
			));
		}

		$cli->info(\sprintf(
			'%d keys in %d groups. %d maps, whose keys are expressions: %s',
			\count($schema['settings']),
			\count($counts),
			\count($schema['maps']),
			\implode(', ', $schema['maps'])
		));

		if ($failed) {
			$cli->error(\sprintf('%d group(s) could not be read.', \count($schema['errors'])), true, 1);
		}
	}

	/**
	 * The settings a browser may read, with this project's values.
	 */
	private function exportForClient(KliArgs $args): void
	{
		$cli    = $this->getCli();
		$export = SettingsSchema::forClient();
		$failed = [] !== $export['errors'];

		if ($args->get('json')) {
			$cli->writeJson($export, !$failed, $failed ? 1 : 0, $failed ? 'OZ_SETTINGS_CLIENT_FROM_ENV' : '');
		}

		foreach ($export['errors'] as $error) {
			$cli->warn(\sprintf('%s: %s', $error['group'], $error['reason']));
		}

		foreach ($export['settings'] as $group => $settings) {
			$cli->writeLn(\sprintf('%s: %d', $group, \count($settings)));

			foreach ($settings as $key => $value) {
				$cli->writeLn(\sprintf('  %-36s %s', $key, \json_encode($value)));
			}
		}

		if ($failed) {
			$cli->error('A setting marked for the client is read from the environment.', true, 1);
		}
	}

	#[Override]
	protected function describe(): void
	{
		$this->description('Manage your project settings.');

		// action: export the settings as declarations
		$export = $this->action('export', 'Export the settings as declarations, for a tool or a reference.');
		$export->option('client', 'c', [], 1)
			->description('Export only the keys a browser may read, with this project\'s values.')
			->bool()
			->def(false);
		self::withJsonSupport($export);
		$export->handler($this->export(...));

		// action: set a setting
		$set = $this->action('set', 'Set a setting in the project.');
		self::commonOptions($set);
		$set->option('value', 'v', [], 4)
			->required()
			->prompt(true, 'The setting value')
			->description('The setting value. Use json format for array or object value.')
			->string();
		$set->option('source', '', [], 5)
			->description('Write to the source settings directory instead of the stateful one. Use during development.')
			->bool();

		$set->handler($this->set(...));

		// action: unset a setting
		$unset = $this->action('unset', 'Unset a setting in the project.');
		self::commonOptions($unset);
		$unset->option('source', '', [], 4)
			->description('Write to the source settings directory instead of the stateful one. Use during development.')
			->bool();
		$unset->handler($this->unset(...));
	}

	private static function commonOptions(KliAction $action): void
	{
		$action->option('scope', 's', [], 1)
			->description('The scope name of the setting; the root scope when not provided.')
			->string();
		$action->option('group', 'g', [], 2)
			->required()
			->prompt(true, 'The setting group')
			->description('The setting group.')
			->string();
		$action->option('key', 'k', [], 3)
			->required()
			->prompt(true, 'The setting key')
			->description('The setting key.')
			->string();
	}

	/**
	 * Parses a string value to its corresponding type (null, boolean, number, array, object, or string).
	 *
	 * @param string $value the string value to parse
	 *
	 * @return mixed the parsed value in its corresponding type
	 */
	private static function parseValue(string $value): mixed
	{
		$value = \trim($value);

		if ('null' === \strtolower($value)) {
			return null;
		}

		if ('true' === \strtolower($value)) {
			return true;
		}

		if ('false' === \strtolower($value)) {
			return false;
		}

		if (\is_numeric($value)) {
			return \str_contains($value, '.') ? (float) $value : (int) $value;
		}

		if ((\str_starts_with($value, '{') && \str_ends_with($value, '}'))
			|| (\str_starts_with($value, '[') && \str_ends_with($value, ']'))
		) {
			$decoded = \json_decode($value, true);

			if (\JSON_ERROR_NONE === \json_last_error()) {
				return $decoded;
			}
		}

		return $value;
	}
}
