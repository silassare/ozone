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

namespace OZONE\Core\App;

use InvalidArgumentException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\FS;
use OZONE\Core\FS\PathSources;
use OZONE\Core\FS\Templates;
use OZONE\Core\Scopes\Interfaces\ScopeInterface;
use OZONE\Core\Utils\Random;
use Throwable;

/**
 * Class Settings.
 */
final class Settings
{
	/**
	 * Setting group name pattern.
	 *
	 * ```
	 * foo.bar.baz        -> ok
	 * foo/bar.baz        -> ok
	 * foo/bar/pop.bob    -> ok
	 * foo.bar.           -> no
	 * foo/./pop.bob      -> no
	 * ```
	 */
	public const PATTERN_SETTING_GROUP_NAME = '(?:[a-z0-9]+/)*[a-z0-9]+(?:\.[a-z0-9]+)*';

	/**
	 * Setting group name regular expression.
	 */
	public const REG_SETTING_GROUP_NAME = '#^' . self::PATTERN_SETTING_GROUP_NAME . '$#';

	/**
	 * Settings groups map.
	 *
	 * @var array<string, SettingsGroup>
	 */
	private static array $settings_groups = [];

	/**
	 * List of not editable settings at runtime.
	 *
	 * @var array<string, null>
	 */
	private static array $settings_blacklist = ['oz.config' => null];

	/**
	 * Settings as loaded array.
	 *
	 * @var array<string, array>
	 */
	private static array $as_loaded = [];

	/**
	 * Gets settings path sources.
	 *
	 * @return PathSources
	 */
	public static function getSources(): PathSources
	{
		/** @var null|PathSources $sources */
		static $sources = null;

		if (null === $sources) {
			$sources = new PathSources();
			$sources->add(OZ_OZONE_DIR . 'oz_settings');
		}

		return $sources;
	}

	/**
	 * Adds settings sources directory.
	 *
	 * @param string $path settings files directory path
	 */
	public static function addSource(string $path): void
	{
		if (!\is_dir($path)) {
			throw new InvalidArgumentException(\sprintf('Invalid directory: %s', $path));
		}

		self::getSources()->add($path);
	}

	/**
	 * Disable a given settings edit at runtime.
	 *
	 * @param string $setting_group_name the setting group name
	 */
	public static function disableRuntimeEdit(string $setting_group_name): void
	{
		self::checkSettingGroupName($setting_group_name);
		self::$settings_blacklist[$setting_group_name] = null;
	}

	/**
	 * Returns settings group data.
	 *
	 * @param string $group  the setting group
	 * @param bool   $reload reload settings from sources
	 *
	 * @return array
	 */
	public static function load(string $group, bool $reload = false): array
	{
		return self::requireGroupStore($group, $reload)->toArray();
	}

	/**
	 * Gets value of a given key in a setting group.
	 *
	 * @param string     $group  the setting group
	 * @param string     $key    the setting key name
	 * @param null|mixed $def    the default value (when not set)
	 * @param bool       $reload reload settings from sources
	 *
	 * @return mixed
	 */
	public static function get(string $group, string $key, mixed $def = null, bool $reload = false): mixed
	{
		$s = self::requireGroupStore($group, $reload);

		if ($s->has($key)) {
			return $s->get($key);
		}

		return $def;
	}

	/**
	 * Sets value of a given key in a setting group.
	 *
	 * @param string              $group the setting group name
	 * @param string              $key   the setting key
	 * @param mixed               $value the setting value
	 * @param null|ScopeInterface $scope the scope to use (default: current app scope)
	 */
	public static function set(
		string $group,
		string $key,
		mixed $value,
		?ScopeInterface $scope = null
	): void {
		self::modify($group, static fn ($current) => $current->set($key, $value), $scope);
	}

	/**
	 * Unsets value of a given key in a setting group.
	 *
	 * @param string              $group the setting group name
	 * @param string              $key   the setting key
	 * @param null|ScopeInterface $scope the scope to use (default: current app scope)
	 */
	public static function unset(
		string $group,
		string $key,
		?ScopeInterface $scope = null
	): void {
		self::modify($group, static fn ($current) => $current->remove($key), $scope);
	}

	/**
	 * Checks if a given setting group exists.
	 * If a key is given, checks if the key exists in the setting group.
	 *
	 * @param string      $group the setting group
	 * @param null|string $key   the setting key name
	 *
	 * @return bool
	 */
	public static function has(string $group, ?string $key = null): bool
	{
		try {
			if (null !== $key) {
				$def = Random::string();
				$val = self::get($group, $key, $def);

				return $val !== $def;
			}

			self::requireGroupStore($group);

			return true;
		} catch (Throwable) {
			return false;
		}
	}

	/**
	 * Applies the merge strategy to two settings arrays.
	 *
	 * @param array $a the first settings array
	 * @param array $b the second settings array
	 *
	 * @return array
	 */
	public static function applyMergeStrategy(array $a, array $b): array
	{
		if (\is_int(\key($a))) {
			return \array_merge($a, $b);
		}

		return \array_replace_recursive($a, $b);
	}

	/**
	 * Generate settings export info usable in template file.
	 *
	 * @param string $group    the setting group name
	 * @param array  $settings the settings
	 *
	 * @return array
	 */
	public static function genExportInfo(string $group, array $settings): array
	{
		return [
			'oz_settings_name' => $group,
			'oz_settings_data' => $settings,
			'oz_settings_str'  => self::export($settings, 1, "\t", true),
		];
	}

	/**
	 * Returns the settings group store instance.
	 *
	 * @param string $group  the setting group name
	 * @param bool   $reload reload settings from sources
	 *
	 * @return SettingsGroup
	 */
	private static function requireGroupStore(string $group, bool $reload = false): SettingsGroup
	{
		self::checkSettingGroupName($group);
		self::loadAll($group, $reload);

		$found = self::$settings_groups[$group] ?? null;

		if (null !== $found) {
			return $found;
		}

		throw new RuntimeException(\sprintf('Undefined setting group: %s', $group));
	}

	/**
	 * Modifies settings group data with a modifier callback function.
	 *
	 * @param string                       $group    the setting group name
	 * @param callable(SettingsGroup):void $modifier the settings modifier callback function
	 * @param null|ScopeInterface          $scope    the scope
	 */
	private static function modify(
		string $group,
		callable $modifier,
		?ScopeInterface $scope = null
	): void {
		self::requireGroupStore($group, true);

		if (\array_key_exists($group, self::$settings_blacklist)) {
			throw new RuntimeException(
				\sprintf(
					'Runtime settings edit is disabled for "%s". Try manually.',
					$group
				)
			);
		}

		$source_dir_fm = ($scope ?? app())->getSettingsDir();
		$relative_path = $group . '.php';
		$abs_path      = $source_dir_fm->resolve($relative_path);

		$current = new SettingsGroup(self::$as_loaded[$abs_path] ?? []);

		$modifier($current);

		$inject = self::genExportInfo($group, $current->toArray());

		try {
			$parts = \pathinfo($abs_path);
			$source_dir_fm->cd($parts['dirname'], true)
				->wf(
					$parts['basename'],
					Templates::compile('oz://~core~/gen/settings.info.blate', $inject)
				);
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to save settings.', null, $t);
		}

		// updates settings
		self::loadAll($group, true);
	}

	/**
	 * Loads all settings file for a given setting group name.
	 *
	 * Loading order:
	 *  - first load from default ozone settings sources dir
	 *  - after load from customs app settings sources dir
	 *
	 * @param string $group  the setting group name
	 * @param bool   $reload reload settings from sources
	 */
	private static function loadAll(string $group, bool $reload = false): void
	{
		if ($reload) {
			unset(self::$settings_groups[$group]);
		}

		if (!\array_key_exists($group, self::$settings_groups)) {
			$list = self::getSources()->getAllSources();

			foreach ($list as $source) {
				$fm       = FS::from($source);
				$abs_path = $fm->resolve($group . '.php');

				if (\file_exists($abs_path)) {
					$result = require $abs_path;

					if (!\is_array($result)) {
						throw new RuntimeException(\sprintf(
							'Settings "%s" returned from "%s" should be of type "array" not "%s"',
							$group,
							$abs_path,
							\get_debug_type($result)
						));
					}

					self::$as_loaded[$abs_path] = $result;

					if (!\array_key_exists($group, self::$settings_groups)) {
						self::$settings_groups[$group] = new SettingsGroup($result);
					} else {
						self::$settings_groups[$group]->merge($result);
					}
				}
			}
		}
	}

	/**
	 * Checks a setting group name validity.
	 *
	 * @param string $setting_group_name the setting group name
	 */
	private static function checkSettingGroupName(string $setting_group_name): void
	{
		if (!\preg_match(self::REG_SETTING_GROUP_NAME, $setting_group_name)) {
			throw new RuntimeException(\sprintf('Invalid setting group name: %s', $setting_group_name));
		}
	}

	/**
	 * A custom var_export function for settings.
	 *
	 * @param mixed  $data        the data to export
	 * @param int    $indent      indent start
	 * @param string $indent_char the indent char to use
	 * @param bool   $align       enable array key align
	 *
	 * @return string
	 */
	private static function export(
		mixed $data,
		int $indent = 0,
		string $indent_char = "\t",
		bool $align = false
	): string {
		if (\is_array($data)) {
			$r          = [];
			$start      = \str_repeat($indent_char, $indent);
			$indexed    = \array_is_list($data);
			$max_length = $align ? \max(\array_map('\strlen', \array_map('trim', \array_keys($data)))) + 2 : 0;

			foreach ($data as $key => $value) {
				if (\is_string($value) && \str_starts_with($key, '::comment::')) {
					$comment = $start . '//= ';
					$comment .= \wordwrap($value, 75, \PHP_EOL . $comment);
					$r[]     = $comment;
				} else {
					$key = self::export($key);
					$r[] = $start . $indent_char
						. ($indexed ? '' : \str_pad($key, $max_length) . ' => ')
						. self::export($value, $indent + 1, $indent_char, $align);
				}
			}

			return \count($r) ? '[' . \PHP_EOL . \implode(',' . \PHP_EOL, $r) . \PHP_EOL . $start . ']' : '[]';
		}

		if (\is_bool($data)) {
			return $data ? 'true' : 'false';
		}

		if (null === $data) {
			return 'null';
		}

		return \var_export($data, true);
	}
}
