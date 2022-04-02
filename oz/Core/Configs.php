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

namespace OZONE\OZ\Core;

use InvalidArgumentException;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\FS\FilesManager;
use OZONE\OZ\FS\TemplatesUtils;
use Throwable;

/**
 * Class Configs.
 */
final class Configs
{
	/**
	 * setting group name regular expression.
	 *
	 * foo.bar.baz        -> ok
	 * foo/bar.baz        -> ok
	 * foo/bar/pop.bob    -> ok
	 * foo.bar.           -> no
	 * foo/./pop.bob      -> no
	 */
	public const REG_SETTING_GROUP_NAME = '#^(?:[a-z0-9]+/)*[a-z0-9]+(?:\.[a-z0-9]+)*$#';

	/**
	 * settings map array.
	 *
	 * @var array
	 */
	private static array $settings_map = [];

	/**
	 * list of not editable settings at runtime.
	 *
	 * @var array
	 */
	private static array $settings_blacklist = ['oz.config' => null, 'oz.keygen.salt' => null];

	/**
	 * settings as loaded array.
	 *
	 * @var array
	 */
	private static array $as_loaded = [];

	/**
	 * ozone settings sources directories.
	 *
	 * we look in that source first
	 *
	 * @var array
	 */
	private static array $oz_sources_dir = [OZ_OZONE_DIR . 'oz_settings'];

	/**
	 * app settings sources directories.
	 *
	 * we look in that source at end
	 *
	 * @var array
	 */
	private static array $app_sources_dir = [OZ_APP_DIR . 'oz_settings'];

	/**
	 * adds settings sources directory.
	 *
	 * @param string $path settings files directory path
	 */
	public static function addSource(string $path): void
	{
		if (!\in_array($path, self::$oz_sources_dir, true) && !\in_array($path, self::$app_sources_dir, true)) {
			if (!\is_dir($path)) {
				throw new InvalidArgumentException(\sprintf('Invalid directory: %s', $path));
			}

			if (\str_starts_with($path, OZ_OZONE_DIR)) {
				self::$oz_sources_dir[] = $path;
			} else {
				self::$app_sources_dir[] = $path;
			}
		}
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
	 *
	 * @return mixed
	 */
	public static function load(string $setting_group): mixed
	{
		self::checkSettingGroupName($setting_group);
		self::loadAll($setting_group);

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
		self::checkSettingGroupName($setting_group);
		self::loadAll($setting_group);

		if (\array_key_exists($setting_group, self::$settings_map)) {
			$data = self::$settings_map[$setting_group];

			if (\array_key_exists($key, $data)) {
				return $data[$key];
			}
		} else {
			throw new RuntimeException(\sprintf('Undefined setting group: %s', $setting_group));
		}

		return $def;
	}

	/**
	 * sets value of a given key in a setting group.
	 *
	 * @param string $setting_group_name the setting group name
	 * @param string $key                the setting key
	 * @param mixed  $value              the setting value
	 */
	public static function set(string $setting_group_name, string $key, mixed $value): void
	{
		$data[$key] = $value;
		self::save($setting_group_name, $data, false);
	}

	/**
	 * sets settings data.
	 *
	 * @param string $setting_group_name the setting group name
	 * @param mixed  $data               setting data
	 * @param bool   $overwrite          overwrite setting with data
	 */
	public static function save(string $setting_group_name, array $data, bool $overwrite = false): void
	{
		self::checkSettingGroupName($setting_group_name);

		if (\array_key_exists($setting_group_name, self::$settings_blacklist)) {
			throw new RuntimeException(
				\sprintf(
					'Runtime settings edit is disabled for "%s". Try manually.',
					$setting_group_name
				)
			);
		}

		$setting_file = OZ_APP_DIR . 'oz_settings' . DS . $setting_group_name . '.php';
		$settings     = self::$as_loaded[$setting_file] ?? [];

		$settings = $overwrite ? $data : \array_replace_recursive($settings, $data);

		$parts  = \pathinfo($setting_file);
		$inject = self::genExportInfo($setting_group_name, $settings);

		try {
			$fm = new FilesManager();
			$fm->cd($parts['dirname'], true)
				->wf($parts['basename'], TemplatesUtils::compile('oz://gen/settings.info.otpl', $inject));
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to save settings.', null, $t);
		}
		// updates settings
		self::$as_loaded[$setting_file] = $settings;
		self::override($setting_group_name, $settings);
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
	 */
	private static function loadAll(string $setting_group_name): void
	{
		if (!\array_key_exists($setting_group_name, self::$settings_map)) {
			$list = [self::$oz_sources_dir, self::$app_sources_dir];

			foreach ($list as $sources) {
				foreach ($sources as $source) {
					$setting_file = $source . DS . $setting_group_name . '.php';

					if (\file_exists($setting_file)) {
						$result = include $setting_file;

						if (!\is_array($result)) {
							throw new RuntimeException(\sprintf(
								'Settings "%s" returned from "%s" should be of type "array" not "%s"',
								$setting_group_name,
								$setting_file,
								\get_debug_type($result)
							));
						}

						self::$as_loaded[$setting_file] = $result;
						self::override($setting_group_name, $result);
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
	 * override settings.
	 *
	 * @param string $setting_group_name the setting group name
	 * @param array  $data
	 */
	private static function override(string $setting_group_name, array $data): void
	{
		if (!\array_key_exists($setting_group_name, self::$settings_map)) {
			self::$settings_map[$setting_group_name] = $data;
		} else {
			self::$settings_map[$setting_group_name] = self::merge(self::$settings_map[$setting_group_name], $data);
		}
	}

	/**
	 * a custom var_export function.
	 *
	 * @param mixed  $data        the data to export
	 * @param int    $indent      indent start
	 * @param string $indent_char the indent char to use
	 * @param bool   $align       enable array key align
	 *
	 * @return string
	 */
	private static function export(mixed $data, int $indent = 0, string $indent_char = "\t", bool $align = false): string
	{
		if (\is_array($data)) {
			$r          = [];
			$start      = \str_repeat($indent_char, $indent);
			$indexed    = \array_keys($data) === \range(0, \count($data) - 1);
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
