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
use OZONE\Core\FS\FilesManager;
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
	 * setting group name pattern.
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
	 * setting group name regular expression.
	 */
	public const REG_SETTING_GROUP_NAME = '#^' . self::PATTERN_SETTING_GROUP_NAME . '$#';

	/**
	 * settings map array.
	 *
	 * @var array
	 */
	private static array $settings_map = [];

	/**
	 * list of not editable settings at runtime.
	 *
	 * @var array<string, null>
	 */
	private static array $settings_blacklist = ['oz.config' => null];

	/**
	 * settings as loaded array.
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
	 * adds settings sources directory.
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
	 * returns settings group data.
	 *
	 * @param string $setting_group the setting group
	 * @param bool   $reload        reload settings from sources
	 *
	 * @return array
	 */
	public static function load(string $setting_group, bool $reload = false): array
	{
		self::checkSettingGroupName($setting_group);
		self::loadAll($setting_group, $reload);

		if (\array_key_exists($setting_group, self::$settings_map)) {
			return self::$settings_map[$setting_group];
		}

		throw new RuntimeException(\sprintf('Undefined setting group: %s', $setting_group));
	}

	/**
	 * gets value of a given key in a setting group.
	 *
	 * @param string     $setting_group the setting group
	 * @param string     $key           the setting key name
	 * @param null|mixed $def           the default value (when not set)
	 *
	 * @return mixed
	 */
	public static function get(string $setting_group, string $key, mixed $def = null): mixed
	{
		$settings = self::load($setting_group);

		if (\array_key_exists($key, $settings)) {
			return $settings[$key];
		}

		return $def;
	}

	/**
	 * sets value of a given key in a setting group.
	 *
	 * @param string              $setting_group_name the setting group name
	 * @param string              $key                the setting key
	 * @param mixed               $value              the setting value
	 * @param null|ScopeInterface $scope              the scope to use
	 */
	public static function set(
		string $setting_group_name,
		string $key,
		mixed $value,
		?ScopeInterface $scope = null
	): void {
		$data[$key] = $value;
		self::save($setting_group_name, $data, true, $scope);
	}

	/**
	 * checks if a given setting group exists.
	 * if a key is given, checks if the key exists in the setting group.
	 *
	 * @param string      $setting_group the setting group
	 * @param null|string $key           the setting key name
	 *
	 * @return bool
	 */
	public static function has(string $setting_group, ?string $key = null): bool
	{
		try {
			if (null !== $key) {
				$def = Random::string();
				$val = self::get($setting_group, $key, $def);

				return $val !== $def;
			}

			self::load($setting_group);

			return true;
		} catch (Throwable) {
			return false;
		}
	}

	/**
	 * sets settings data.
	 *
	 * @param string              $setting_group_name the setting group name
	 * @param mixed               $data               settings data
	 * @param bool                $merge              merge with existing data or not
	 * @param null|ScopeInterface $scope              the scope
	 */
	public static function save(
		string $setting_group_name,
		array $data,
		bool $merge = false,
		?ScopeInterface $scope = null
	): void {
		self::checkSettingGroupName($setting_group_name);

		if (\array_key_exists($setting_group_name, self::$settings_blacklist)) {
			throw new RuntimeException(
				\sprintf(
					'Runtime settings edit is disabled for "%s". Try manually.',
					$setting_group_name
				)
			);
		}

		$source_dir_fm         = ($scope ?? app())->getSettingsDir();
		$setting_relative_path = $setting_group_name . '.php';
		$setting_abs_path      = $source_dir_fm->resolve($setting_relative_path);

		// make sure that settings are loaded
		self::loadAll($setting_group_name, true);

		$current = self::$as_loaded[$setting_abs_path] ?? [];

		$settings = $merge ? self::merge($current, $data) : $data;

		$inject = self::genExportInfo($setting_group_name, $settings);

		try {
			$parts = \pathinfo($setting_abs_path);
			$source_dir_fm->cd($parts['dirname'], true)
				->wf(
					$parts['basename'],
					Templates::compile('oz://gen/settings.info.otpl', $inject)
				);
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to save settings.', null, $t);
		}

		// updates settings
		self::loadAll($setting_group_name, true);
	}

	/**
	 * settings merge strategy.
	 *
	 * @param array $current
	 * @param array $data
	 *
	 * @return array
	 */
	public static function merge(array $current, array $data): array
	{
		if (\is_int(\key($current))) {
			return \array_merge($current, $data);
		}

		return \array_replace_recursive($current, $data);
	}

	/**
	 * generate settings export info usable in template file.
	 *
	 * @param string $setting_group_name the setting group name
	 * @param array  $settings           the settings
	 *
	 * @return array
	 */
	public static function genExportInfo(string $setting_group_name, array $settings): array
	{
		return [
			'oz_version_name'  => OZ_OZONE_VERSION_NAME,
			'oz_time'          => \time(),
			'oz_settings_name' => $setting_group_name,
			'oz_settings_data' => $settings,
			'oz_settings_str'  => self::export($settings, 1, "\t", true),
		];
	}

	/**
	 * Loads all settings file for a given setting group name.
	 *
	 * Loading order:
	 *  - first load from default ozone settings sources dir
	 *  - after load from customs app settings sources dir
	 *
	 * @param string $setting_group_name the setting group name
	 * @param bool   $reload             reload settings from sources
	 */
	private static function loadAll(string $setting_group_name, bool $reload = false): void
	{
		if ($reload) {
			unset(self::$settings_map[$setting_group_name]);
		}

		if (!\array_key_exists($setting_group_name, self::$settings_map)) {
			$list = self::getSources()->getAllSources();

			foreach ($list as $source) {
				$fm               = new FilesManager($source);
				$setting_abs_path = $fm->resolve($setting_group_name . '.php');

				if (\file_exists($setting_abs_path)) {
					$result = require $setting_abs_path;

					if (!\is_array($result)) {
						throw new RuntimeException(\sprintf(
							'Settings "%s" returned from "%s" should be of type "array" not "%s"',
							$setting_group_name,
							$setting_abs_path,
							\get_debug_type($result)
						));
					}

					self::$as_loaded[$setting_abs_path] = $result;

					if (!\array_key_exists($setting_group_name, self::$settings_map)) {
						self::$settings_map[$setting_group_name] = $result;
					} else {
						$current                                 = self::$settings_map[$setting_group_name];
						self::$settings_map[$setting_group_name] = self::merge($current, $result);
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
	 * a custom var_export function for settings.
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
