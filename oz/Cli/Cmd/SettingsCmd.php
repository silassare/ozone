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
use OZONE\Core\Cli\Command;

/**
 * Class SettingsCmd.
 */
final class SettingsCmd extends Command
{
	/**
	 * Upsets a new setting in the project.
	 */
	public function upset(
		KliArgs $args
	): void {
		$scope_name      = $args->get('scope');
		$group           = $args->get('group');
		$key             = $args->get('key');
		$value           = self::parseValue($args->get('value'));

		Settings::set($key, $value, $group, scope($scope_name));

		$cli   = $this->getCli();
		$style = $cli->style()->green();

		$cli->success(\sprintf(
			'Setting %s in group %s has been successfully updated.',
			$style->apply($key),
			$style->apply($group)
		));
	}

	/**
	 * Unsets a setting in the project.
	 *
	 * @param KliArgs $args
	 */
	public function unset(
		KliArgs $args
	): void {
		$scope_name      = $args->get('scope');
		$group           = $args->get('group');
		$key             = $args->get('key');

		Settings::unset($key, $group, scope($scope_name));

		$cli   = $this->getCli();
		$style = $cli->style()->green();

		$cli->success(\sprintf(
			'Setting %s in group %s has been successfully unset.',
			$style->apply($key),
			$style->apply($group)
		));
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	protected function describe(): void
	{
		$this->description('Manage your project settings.');

		// action: upset a setting
		$upset = $this->action('upset', 'Upset a setting in the project.');
		self::commonOptions($upset);
		$upset->option('value', 'v', [], 4)
			->required()
			->prompt(true, 'The setting value')
			->description('The setting value. Use json format for array or object value.')
			->string();

		$upset->handler($this->upset(...));

		// action: unset a setting
		$unset = $this->action('unset', 'Unset a setting in the project.');
		self::commonOptions($unset);
		$unset->handler($this->unset(...));
	}

	private static function commonOptions(KliAction $action): void
	{
		$action->option('scope', 's', [], 1)
			->description('The scope name of the setting, if not provided, the setting will be added to the root scope.')
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
			return false !== \strpos($value, '.') ? (float) $value : (int) $value;
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
