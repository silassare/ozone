<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Core;

use Exception;
use InvalidArgumentException;
use OZONE\OZ\Exceptions\InternalErrorException;
use OZONE\OZ\FS\FilesManager;
use OZONE\OZ\FS\TemplatesUtils;

final class SettingsManager
{
	/**
	 * setting group name regular expression
	 *
	 * foo.bar.baz        -> ok
	 * foo/bar.baz        -> ok
	 * foo/bar/pop.bob    -> ok
	 * foo.bar.           -> no
	 * foo/./pop.bob      -> no
	 */
	const REG_SETTING_GROUP_NAME = '#^(?:[a-z0-9]+/)*(?:[a-z0-9]+(?:\.[a-z0-9]+)*)$#';

	/**
	 * settings map array
	 *
	 * @var array
	 */
	private static $settings_map = [];

	/**
	 * list of not editable settings at runtime
	 *
	 * @var array
	 */
	private static $settings_blacklist = ['oz.config' => null, 'oz.keygen.salt' => null];

	/**
	 * settings as loaded cache array
	 *
	 * @var array
	 */
	private static $as_loaded = [];

	/**
	 * ozone settings sources directories
	 *
	 * we look in that source first
	 *
	 * @var array
	 */
	private static $oz_sources_dir = [OZ_OZONE_DIR . 'oz_settings'];

	/**
	 * app settings sources directories
	 *
	 * we look in that source at end
	 *
	 * @var array
	 */
	private static $app_sources_dir = [OZ_APP_DIR . 'oz_settings'];

	/**
	 * adds settings sources directory.
	 *
	 * @param string $path settings files directory path
	 */
	public static function addSource($path)
	{
		if (!\in_array($path, self::$oz_sources_dir) && !\in_array($path, self::$app_sources_dir)) {
			if (!\is_dir($path)) {
				throw new InvalidArgumentException(\sprintf('Invalid directory: %s', $path));
			}

			if (\strpos($path, OZ_OZONE_DIR) === 0) {
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
	public static function disableRuntimeEdit($setting_group_name)
	{
		self::checkSettingGroupName($setting_group_name);
		self::$settings_blacklist[$setting_group_name] = null;
	}

	/**
	 * returns settings data.
	 *
	 * @param string $setting_group_name the setting group name
	 * @param string $key                setting key name
	 * @param mixed  $def
	 *
	 * @return mixed
	 */
	public static function get($setting_group_name, $key = null, $def = null)
	{
		self::checkSettingGroupName($setting_group_name);
		self::loadAll($setting_group_name);

		if (\array_key_exists($setting_group_name, self::$settings_map)) {
			$data = self::$settings_map[$setting_group_name];

			if (null === $key) {
				return $data;
			}

			if (\array_key_exists($key, $data)) {
				return $data[$key];
			}
		} else {
			\trigger_error(\sprintf('Undefined setting group: %s', $setting_group_name), \E_USER_ERROR);
		}

		return $def;
	}

	/**
	 * sets setting key value.
	 *
	 * @param string $setting_group_name the setting group name
	 * @param mixed  $key                a setting key
	 * @param mixed  $value              setting key value
	 *
	 * @throws \OZONE\OZ\Exceptions\BaseException
	 */
	public static function setKey($setting_group_name, $key, $value)
	{
		$data[$key] = $value;
		self::set($setting_group_name, $data, false);
	}

	/**
	 * sets settings data.
	 *
	 * @param string $setting_group_name the setting group name
	 * @param mixed  $data               setting data
	 * @param bool   $overwrite          overwrite setting with data
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 */
	public static function set($setting_group_name, array $data, $overwrite = false)
	{
		self::checkSettingGroupName($setting_group_name);

		if (\array_key_exists($setting_group_name, self::$settings_blacklist)) {
			\trigger_error(\sprintf('Runtime settings edit is disabled for "%s". Try manually.', $setting_group_name), \E_USER_ERROR);
		}

		$setting_file = OZ_APP_DIR . 'oz_settings' . DS . $setting_group_name . '.php';
		$settings     = (isset(self::$as_loaded[$setting_file])) ? self::$as_loaded[$setting_file] : [];

		$settings = $overwrite ? $data : \array_replace_recursive($settings, $data);

		$parts  = \pathinfo($setting_file);
		$inject = self::genExportInfo($setting_group_name, $settings);

		try {
			$fm = new FilesManager();
			$fm->cd($parts['dirname'], true)
			   ->wf($parts['basename'], TemplatesUtils::compute('oz:gen/settings.info.otpl', $inject));
		} catch (Exception $e) {
			throw new InternalErrorException('Unable to save settings.', null, $e);
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
	public static function merge(array $current, array $data)
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
	public static function genExportInfo($setting_group_name, array $settings)
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
	private static function loadAll($setting_group_name)
	{
		if (!\array_key_exists($setting_group_name, self::$settings_map)) {
			$list = [self::$oz_sources_dir, self::$app_sources_dir];

			foreach ($list as $sources) {
				foreach ($sources as $source) {
					$setting_file = $source . DS . $setting_group_name . '.php';

					if (\file_exists($setting_file)) {
						$result = include $setting_file;

						if (!\is_array($result)) {
							\trigger_error(\sprintf('Settings "%s" returned from "%s" should be of type "array" not "%s"', $setting_group_name, $setting_file, \gettype($result)), \E_USER_ERROR);
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
	private static function checkSettingGroupName($setting_group_name)
	{
		if (!\preg_match(self::REG_SETTING_GROUP_NAME, $setting_group_name)) {
			\trigger_error(\sprintf('Invalid setting group name: %s', $setting_group_name), \E_USER_ERROR);
		}
	}

	/**
	 * override settings.
	 *
	 * @param string $setting_group_name the setting group name
	 * @param array  $data
	 */
	private static function override($setting_group_name, array $data)
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
	private static function export($data, $indent = 0, $indent_char = "\t", $align = false)
	{
		if (\is_array($data)) {
			$r          = [];
			$start      = \str_repeat($indent_char, $indent);
			$indexed    = \array_keys($data) === \range(0, \count($data) - 1);
			$max_length = $align ? \max(\array_map('strlen', \array_map('trim', \array_keys($data)))) + 2 : 0;

			foreach ($data as $key => $value) {
				if (0 === \strpos($key, ':oz:comment:') && \is_string($value)) {
					$start   = "\t";
					$comment = $start . '// ';
					$comment .= \wordwrap($value, 75, \PHP_EOL . $comment);
					$r[]     = $comment;
				} else {
					$key = self::export($key);
					$r[] = $start . $indent_char . ($indexed ? '' : \str_pad($key, $max_length) . ' => ') . self::export($value, $indent + 1, $indent_char, $align);
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
