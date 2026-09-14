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

use FilesystemIterator;
use InvalidArgumentException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\FS\PathSources;
use OZONE\Core\FS\Templates;
use OZONE\Core\OZone;
use OZONE\Core\Scopes\Interfaces\ScopeInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use stdClass;
use Throwable;
use UnitEnum;

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
	 * Each loaded group's merged data, read directly by {@see self::get()} for a top-level key.
	 *
	 * A settings lookup happens a few hundred times per request: going through the group's store
	 * parses the key as a dot path every time. Dropped whenever the group is reloaded or modified.
	 *
	 * @var array<string, array>
	 */
	private static array $values = [];

	/**
	 * Whether source directories are read from their compiled bundles ({@see self::sourceBundle()}):
	 * outside the console, in production. Null until the app runs, which tells.
	 */
	private static ?bool $bundles = null;

	/**
	 * The groups of each source directory read from its bundle; false for a directory read file by
	 * file.
	 *
	 * @var array<string, array<string, array>|false>
	 */
	private static array $source_bundles = [];

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
	 * @param string $path     settings files directory path
	 * @param bool   $stateful whether it holds stateful settings, written at runtime (`data/settings/...`):
	 *                         never read from a compiled bundle
	 */
	public static function addSource(string $path, bool $stateful = false): void
	{
		if (!\is_dir($path)) {
			throw new InvalidArgumentException(\sprintf('Invalid directory: %s', $path));
		}

		self::getSources()->add($path, $stateful);
	}

	/**
	 * Compiles the bundle of every source directory registered so far, as production reads them.
	 *
	 * @internal run by `oz project build` for each scope
	 *
	 * @return int the directories bundled
	 */
	public static function warmBundles(): int
	{
		$sources = self::getSources();
		$count   = 0;

		foreach ($sources->getAllSources() as $source) {
			if (!$sources->isStateful($source) && null !== self::sourceBundle($source)) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Forces the compiled bundles of the source directories on or off, or back to deciding (null).
	 *
	 * @internal for tests: bundles are otherwise used outside the console, in production
	 */
	public static function useBundles(?bool $enabled): void
	{
		self::$bundles        = $enabled;
		self::$source_bundles = [];
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
		if (!$reload && isset(self::$values[$group])) {
			return self::$values[$group];
		}

		return self::$values[$group] = self::requireGroupStore($group, $reload)->toArray();
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
		// A top-level key (no `.` path, no `[...]` segment) is a direct lookup in the group's data.
		if (!\str_contains($key, '.') && !\str_contains($key, '[')) {
			$values = $reload ? self::load($group, true) : (self::$values[$group] ?? self::load($group));

			return \array_key_exists($key, $values) ? $values[$key] : $def;
		}

		$s = self::requireGroupStore($group, $reload);

		if ($s->has($key)) {
			return $s->get($key);
		}

		return $def;
	}

	/**
	 * Sets value of a given key in a setting group.
	 *
	 * @param string              $group    the setting group name
	 * @param string              $key      the setting key
	 * @param mixed               $value    the setting value
	 * @param null|ScopeInterface $scope    the scope to use (default: current app scope)
	 * @param bool                $stateful true -> stateful dir (data/settings/), false -> source dir (app/settings/)
	 */
	public static function set(
		string $group,
		string $key,
		mixed $value,
		?ScopeInterface $scope = null,
		bool $stateful = true
	): void {
		self::modify($group, static fn ($current) => $current->set($key, $value), $scope, $stateful);
	}

	/**
	 * Unsets value of a given key in a setting group.
	 *
	 * @param string              $group    the setting group name
	 * @param string              $key      the setting key
	 * @param null|ScopeInterface $scope    the scope to use (default: current app scope)
	 * @param bool                $stateful true -> stateful dir (data/settings/), false -> source dir (app/settings/)
	 */
	public static function unset(
		string $group,
		string $key,
		?ScopeInterface $scope = null,
		bool $stateful = true
	): void {
		self::modify($group, static fn ($current) => $current->remove($key), $scope, $stateful);
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
				// A fresh object is a default no stored value can be identical to.
				$def = new stdClass();

				return self::get($group, $key, $def) !== $def;
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
	 * @param null|ScopeInterface          $scope    the scope to use (default: current app scope)
	 * @param bool                         $stateful true -> stateful dir (data/), false -> source dir (app/)
	 */
	private static function modify(
		string $group,
		callable $modifier,
		?ScopeInterface $scope = null,
		bool $stateful = true
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
		$target        = $scope ?? app();
		$source_dir_fm = $stateful ? $target->getStatefulSettingsDir() : $target->getSettingsDir();
		// The same path loadAll() keys $as_loaded with: a different spelling would start the edit
		// from an empty group and write back only the edited key.
		$abs_path      = self::groupFile($source_dir_fm->getRoot(), $group);

		$current = new SettingsGroup(self::$as_loaded[$abs_path] ?? []);

		$modifier($current);

		$inject = self::genExportInfo($group, $current->toArray());

		// Taken before cd() below moves the manager to the group file's directory.
		$root       = $stateful ? $source_dir_fm->getRoot() : null;
		$root_mtime = null === $root ? false : \filemtime($root);

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

		if (null !== $root) {
			// Routers key their route table by this directory's mtime (OZone::routeTableFile()), and the
			// settings may change what the routes are: it must move on every edit. Rewriting a file leaves
			// it as it was, and so would an edit within the second it was last set (it is read in
			// seconds), hence at least one second past it.
			@\touch($root, \max(\time(), false === $root_mtime ? 0 : $root_mtime + 1));
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
			unset(self::$settings_groups[$group], self::$values[$group]);
		}

		if (!\array_key_exists($group, self::$settings_groups)) {
			$sources = self::getSources();
			$bundles = self::bundlesEnabled();

			foreach ($sources->getAllSources() as $source) {
				// A directory shipped with the code: its bundle holds every group it has.
				if ($bundles && !$sources->isStateful($source)) {
					$bundle = self::sourceBundle($source);

					if (null !== $bundle) {
						if (isset($bundle[$group])) {
							self::mergeGroup($group, $bundle[$group]);
						}

						continue;
					}
				}

				$abs_path = self::groupFile($source, $group);

				if (\is_file($abs_path)) {
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

					self::mergeGroup($group, $result);
				}
			}
		}
	}

	/**
	 * Merges a source's values of a group into what the sources before it gave.
	 */
	private static function mergeGroup(string $group, array $values): void
	{
		if (!\array_key_exists($group, self::$settings_groups)) {
			self::$settings_groups[$group] = new SettingsGroup($values);
		} else {
			self::$settings_groups[$group]->merge($values);
		}
	}

	/**
	 * Whether source directories are read from their compiled bundles.
	 */
	private static function bundlesEnabled(): bool
	{
		if (null !== self::$bundles) {
			return self::$bundles;
		}

		// Not decided before the app runs: production is told by its .env.
		if (!OZone::isRunning()) {
			return false;
		}

		return self::$bundles = !OZone::isCliMode() && OZone::inProductionMode();
	}

	/**
	 * The groups of a source directory from its compiled bundle, written when missing: null for a
	 * directory read file by file.
	 *
	 * Instead of a stat per group and source, one array per source, which OPcache keeps in memory. The
	 * bundle is kept in the app's project directory and named after the directory, the release (the
	 * project directory: a settings file may read it), OZone's version, the `.env` file (read the same
	 * way) and the directory's mtime (a deployment updating files in place writes them by renaming). A
	 * directory that cannot be bundled -- a group holding an object, which the bundle could not write
	 * back, or failing to load -- is read file by file.
	 *
	 * @return null|array<string, array>
	 */
	private static function sourceBundle(string $dir): ?array
	{
		if (\array_key_exists($dir, self::$source_bundles)) {
			$bundle = self::$source_bundles[$dir];

			return false === $bundle ? null : $bundle;
		}

		if (!\is_dir($dir)) {
			return null;
		}

		$root  = \rtrim(app()->getProjectDir()->getRoot(), '/\\');
		$cache = $root . DS . '.ozone' . DS . 'cache' . DS . 'settings';
		$name  = \hash('xxh128', $dir);
		$file  = $cache . DS . $name . '.' . \hash('xxh128', \serialize([
			$root,
			OZ_OZONE_VERSION,
			app()->getEnv()->getSignature(),
			\filemtime($dir),
		])) . '.php';

		if (\is_file($file)) {
			$groups = include $file;

			if (\is_array($groups)) {
				return self::$source_bundles[$dir] = $groups;
			}
		}

		$groups = self::compileBundle($dir);

		self::$source_bundles[$dir] = $groups ?? false;

		if (null !== $groups) {
			self::writeBundle($cache, $name, $file, $groups);
		}

		return $groups;
	}

	/**
	 * Every group of a source directory: null when one cannot be bundled.
	 *
	 * @return null|array<string, array>
	 */
	private static function compileBundle(string $dir): ?array
	{
		$root   = \rtrim($dir, '/\\') . DS;
		$groups = [];

		try {
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
			);

			foreach ($files as $file) {
				$path = $file->getPathname();

				if (!\str_ends_with($path, '.php')) {
					continue;
				}

				$group = \str_replace(DS, '/', \substr($path, \strlen($root), -4));

				if (!\preg_match(self::REG_SETTING_GROUP_NAME, $group)) {
					continue;
				}

				$result = require $path;

				if (!\is_array($result) || !self::isExportable($result)) {
					return null;
				}

				$groups[$group] = $result;
			}
		} catch (Throwable) {
			return null;
		}

		\ksort($groups);

		return $groups;
	}

	/**
	 * Whether var_export() writes a value back as it is: scalars, nulls, enum cases, arrays of them.
	 */
	private static function isExportable(mixed $value): bool
	{
		if (\is_array($value)) {
			foreach ($value as $item) {
				if (!self::isExportable($item)) {
					return false;
				}
			}

			return true;
		}

		return !\is_object($value) || $value instanceof UnitEnum;
	}

	/**
	 * Writes a source directory's bundle, in place of its earlier ones.
	 *
	 * @param array<string, array> $groups
	 */
	private static function writeBundle(string $cache, string $name, string $file, array $groups): void
	{
		// Best effort: this process has the groups, and the next one compiles them again.
		try {
			if (!\is_dir($cache) && !\mkdir($cache, 0o775, true) && !\is_dir($cache)) {
				return;
			}

			foreach (\glob($cache . DS . $name . '.*.php') ?: [] as $old) {
				\unlink($old);
			}

			(new FilesManager($cache))->writeAtomic(
				\basename($file),
				'<?php' . \PHP_EOL . \PHP_EOL . '// Compiled by OZone from a settings directory: see Settings.'
					. \PHP_EOL . \PHP_EOL . 'return ' . \var_export($groups, true) . ';' . \PHP_EOL
			);
		} catch (Throwable) {
			// another process got there first, or the cache directory is not writable
		}
	}

	/**
	 * The file of a settings group in a source directory.
	 *
	 * A plain concatenation: it runs for every group and source a request loads (a few dozen times),
	 * where a FilesManager and a path resolution each cost more than the lookup. The group name is
	 * validated (no `..`, see REG_SETTING_GROUP_NAME) and the sources are absolute directories.
	 */
	private static function groupFile(string $source, string $group): string
	{
		return \rtrim($source, '/\\') . \DIRECTORY_SEPARATOR . $group . '.php';
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
			$r       = [];
			$start   = \str_repeat($indent_char, $indent);
			$indexed = \array_is_list($data);
			$keys    = \array_keys($data);
			// max() fails on empty arrays; key-alignment only applies to non-empty assoc arrays.
			$max_length = ($align && !$indexed && !empty($keys))
				? \max(\array_map('\strlen', \array_map('trim', $keys))) + 2
				: 0;

			foreach ($data as $key => $value) {
				if (!$indexed && \is_string($key) && \str_starts_with($key, '::comment::') && \is_string($value)) {
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
