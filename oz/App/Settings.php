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

use Exception;
use FilesystemIterator;
use Generator;
use InvalidArgumentException;
use OZONE\Core\App\Interfaces\AppInterface;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\FS\PathSources;
use OZONE\Core\FS\Templates;
use OZONE\Core\OZone;
use OZONE\Core\Plugins\CorePlugin;
use OZONE\Core\Scopes\Interfaces\ScopeInterface;
use OZONE\Core\Scopes\StateLayout;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use stdClass;
use Throwable;
use UnitEnum;

/**
 * Class Settings.
 *
 * @psalm-type SettingsLayer = array{
 *     groups: array<string, SettingsGroup>,
 *     strategies: array<string, array<string, string>>,
 *     locked: array<string, array<string, true>>,
 *     values: array<string, array>,
 *     loading: array<string, true>,
 *     sources: PathSources,
 * }
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
	public const string PATTERN_SETTING_GROUP_NAME = '(?:[a-z0-9]+/)*[a-z0-9]+(?:\.[a-z0-9]+)*';

	/**
	 * Setting group name regular expression.
	 */
	public const string REG_SETTING_GROUP_NAME = '#^' . self::PATTERN_SETTING_GROUP_NAME . '$#';

	/**
	 * The key a settings file declares its merge strategies under, which never reaches the values.
	 *
	 * ```php
	 * return [
	 *     Settings::MERGE_KEY => ['OZ_REDIRECT_ALLOWED_HOSTS' => Settings::MERGE_LOCK],
	 *     'OZ_REDIRECT_ALLOWED_HOSTS' => ['example.com'],
	 * ];
	 * ```
	 */
	public const string MERGE_KEY = '@merge';

	/** Maps merge key by key, and everything else is replaced: what a key does when nothing says. */
	public const string MERGE_DEFAULT = 'default';

	/** The value of a later source replaces what an earlier one gave, whatever its shape. */
	public const string MERGE_REPLACE = 'replace';

	/** Lists are appended, maps merged: for a key every source adds to. */
	public const string MERGE_APPEND = 'append';

	/**
	 * No source may change the key after the one that declared it.
	 *
	 * What an allow-list needs: a plugin loaded after the application cannot widen it, and a later
	 * source that tries is a loud failure rather than a quiet hole.
	 */
	public const string MERGE_LOCK = 'lock';

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
	 * The strategies each group declared, by group then key.
	 *
	 * @var array<string, array<string, string>>
	 */
	private static array $merge_strategies = [];

	/**
	 * Which source locked a key, to name it when a later one tries to change it.
	 *
	 * @var array<string, array<string, true>>
	 */
	private static array $locked = [];

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
	 * Why a source directory could not be bundled, by directory.
	 *
	 * A directory that cannot be bundled is read file by file, which is slower and not wrong, so
	 * {@see self::sourceBundle()} carries on. But a build is held to more than that: a release whose
	 * settings did not compile is a release built with less than it asked for, and saying so needs
	 * the reason and not only a count that came out one short.
	 *
	 * @var array<string, string>
	 */
	private static array $bundle_failures = [];

	/**
	 * The groups being loaded, and the source directories being compiled, right now.
	 *
	 * The memos of this class say what is **finished**: a group is in `self::$settings_groups` once
	 * {@see self::mergeGroup()} has run, a directory in `self::$source_bundles` once
	 * {@see self::compileBundle()} has returned. Neither is in place while a group's file is being
	 * run -- and running a group's file is `require`, so it may read a setting, directly or through
	 * anything it calls. Asked again at that moment, both memos answer "not done" and the work
	 * starts over, from inside itself.
	 *
	 * That is not a slow path, it is unbounded: a production deploy's scope build turned it into
	 * 65 530 open handles on one `app/settings` directory -- one `RecursiveDirectoryIterator` per
	 * turn -- then 4 GB of heap on a 3.9 GB host, and the process was killed. The three days before
	 * it, the same loop reached the descriptor limit first, where the failure to open one more file
	 * was caught as "this directory cannot be bundled" and the release was built one settings bundle
	 * short.
	 *
	 * So what is in progress is registered on entry, by the thing that is about to do the work.
	 *
	 * @var array<string, true>
	 */
	private static array $loading_groups = [];

	/**
	 * @var array<string, true>
	 *
	 * @see self::$loading_groups
	 */
	private static array $compiling_sources = [];

	/**
	 * The settings sources of the scope in use: {@see self::getSources()}.
	 */
	private static ?PathSources $sources = null;

	/**
	 * The settings of each scope this process has switched from or to, by scope name, kept while
	 * another scope's are in use ({@see self::enterScope()}): its groups, values, strategies, locks,
	 * the groups it is loading and its sources. A switch back to a scope is then a few array moves,
	 * arrays being copied on write.
	 *
	 * What is keyed by a directory or a file is shared by every scope, since the same directory
	 * holds the same groups whichever scope reads it: the bundles, the files as loaded, the
	 * directories being compiled.
	 *
	 * @var array<string, SettingsLayer>
	 */
	private static array $layers = [];

	/**
	 * The scopes whose settings are being put together right now ({@see self::enterScope()}).
	 *
	 * @var array<string, true>
	 */
	private static array $building_layers = [];

	/**
	 * The settings of a scope being put together, while a settings file of it has switched to
	 * another scope: kept apart from {@see self::$layers}, which holds finished ones only, so that a
	 * switch back into the scope from there finds nothing to use and is refused, rather than taking
	 * a half-built layer for a finished one -- one whose shared groups were never all compared.
	 *
	 * @var array<string, SettingsLayer>
	 */
	private static array $unfinished_layers = [];

	/**
	 * The scopes whose settings were compared on the groups a process reads once
	 * ({@see self::enterScope()}): settings put together for a read alone are not, until a caller
	 * enters them for more.
	 *
	 * @var array<string, true>
	 */
	private static array $compared_layers = [];

	/**
	 * Gets settings path sources.
	 */
	public static function getSources(): PathSources
	{
		if (null === self::$sources) {
			self::$sources = new PathSources();
			self::$sources->add(CorePlugin::settingsSource());
		}

		return self::$sources;
	}

	/**
	 * Puts the settings of another scope in place of those in use, until {@see self::leaveScope()}.
	 *
	 * Another scope's settings are the same sources with that scope's own directories (its source
	 * settings, its stateful ones) in place of the scope's being left: what its own entry point
	 * would have added. They are put together once per process and kept, so going back and forth
	 * costs a few array moves and {@see self::get()} is untouched.
	 *
	 * Some groups are read once per process and wired for good -- plugins, boot receivers, the
	 * database, the stores, the logs -- so a scope whose own directories declare one of them
	 * otherwise could not be served by this process at all: what it said would be read and never
	 * acted on. Unless the caller only reads values (`$shared_groups` null), each such group either
	 * scope declares in its own directories is compared the first time, and a difference is refused,
	 * naming the group. A scope's settings put together for a read alone are compared the first time
	 * they are entered for more.
	 *
	 * @internal for {@see OZone::inScope()}
	 *
	 * @param ScopeInterface    $from          the scope whose settings are in use
	 * @param ScopeInterface    $to            the scope whose settings to use
	 * @param null|list<string> $shared_groups the groups read once per process: each one, and every
	 *                                         group under it (`oz.db` and `oz.db.*`); null to compare
	 *                                         nothing, for a caller that reads values and wires
	 *                                         nothing
	 *
	 * @throws RuntimeException when the settings of `$to` are asked for while they are being put
	 *                          together or compared, or when a shared group differs; the settings of
	 *                          `$from` are then still in use
	 */
	public static function enterScope(ScopeInterface $from, ScopeInterface $to, ?array $shared_groups): void
	{
		$from_name = $from->getName();
		$to_name   = $to->getName();
		$known     = self::$layers[$to_name] ?? null;
		$compare   = null !== $shared_groups && !isset(self::$compared_layers[$to_name]);

		if (null !== $known && !$compare) {
			self::keep($from_name, self::layer());

			self::useLayer($known);

			return;
		}

		// Registered while it is put together or compared, which runs settings files that may reach
		// for the scope again: the layer is not there yet, or not known to fit, and doing the work a
		// second time inside the first is doing it without end.
		if (isset(self::$building_layers[$to_name])) {
			throw new RuntimeException(\sprintf(
				'The settings of the scope "%s" were asked for while they were being put together.',
				$to_name
			));
		}

		self::$building_layers[$to_name] = true;

		try {
			$leaving  = self::scopeSources($from);
			$entering = self::scopeSources($to);
			$compared = $compare
				? self::sharedGroupsIn([...\array_keys($leaving), ...\array_keys($entering)], $shared_groups ?? [])
				: [];
			$before   = [];

			foreach ($compared as $group) {
				$before[$group] = self::loadIfDeclared($group);
			}

			$outer = self::layer();

			self::keep($from_name, $outer);

			if (null === $known) {
				$sources = $outer['sources']->without(...\array_keys($leaving));

				foreach ($entering as $dir => $stateful) {
					$sources->add($dir, $stateful);
				}

				$known = [
					'groups'     => [],
					'strategies' => [],
					'locked'     => [],
					'values'     => [],
					'loading'    => [],
					'sources'    => $sources,
				];
			}

			self::useLayer($known);

			try {
				foreach ($compared as $group) {
					if (self::loadIfDeclared($group) !== $before[$group]) {
						throw new RuntimeException(\sprintf(
							'The scope "%s" declares the settings group "%s" otherwise than the scope "%s":'
								. ' this process read it once and wired what it says for "%s", so it cannot'
								. ' serve "%s".',
							$to_name,
							$group,
							$from_name,
							$from_name,
							$to_name
						));
					}
				}
			} catch (Throwable $t) {
				self::useLayer($outer);

				throw $t;
			}

			if ($compare) {
				self::$compared_layers[$to_name] = true;
			}
		} finally {
			unset(self::$building_layers[$to_name]);
		}
	}

	/**
	 * Puts back the settings of the scope {@see self::enterScope()} left, keeping those of the scope
	 * it entered for the next time.
	 *
	 * @internal for {@see OZone::inScope()}
	 *
	 * @param ScopeInterface $to   the scope whose settings are in use
	 * @param ScopeInterface $back the scope whose settings to use again
	 */
	public static function leaveScope(ScopeInterface $to, ScopeInterface $back): void
	{
		$name = $back->getName();
		// An unfinished layer is the newer one: the finished one kept before it is being compared.
		$layer = self::$unfinished_layers[$name] ?? self::$layers[$name] ?? null;

		if (null === $layer) {
			throw new RuntimeException(\sprintf('No settings were kept for the scope "%s".', $name));
		}

		self::keep($to->getName(), self::layer());

		unset(self::$unfinished_layers[$name]);

		self::useLayer($layer);
	}

	/**
	 * Keeps the settings of a scope while another's are in use: with the finished ones, or apart
	 * while they are being put together ({@see self::$unfinished_layers}).
	 *
	 * @param SettingsLayer $layer
	 */
	private static function keep(string $scope, array $layer): void
	{
		if (isset(self::$building_layers[$scope])) {
			self::$unfinished_layers[$scope] = $layer;
		} else {
			self::$layers[$scope] = $layer;
		}
	}

	/**
	 * Adds settings sources directory.
	 *
	 * @param string $path     settings files directory path
	 * @param bool   $stateful whether it holds stateful settings, written at runtime (`data/.../settings`):
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
	 * Answers how many were bundled **and** how many asked to be, because the two differ silently:
	 * a directory that fails is read file by file instead ({@see self::sourceBundle()}), so the only
	 * sign of it is a count nobody can judge on its own. `failed` says which directories and why,
	 * so a build reports the reason rather than the shortfall.
	 *
	 * @internal run by `oz project build` for each scope
	 *
	 * @return array{bundled: int, expected: int, failed: array<string, string>}
	 */
	public static function warmBundles(): array
	{
		$sources  = self::getSources();
		$bundled  = 0;
		$expected = 0;
		$failed   = [];

		foreach ($sources->getAllSources() as $source) {
			// Stateful sources are written at runtime and never bundled: they are not expected to be.
			if ($sources->isStateful($source)) {
				continue;
			}

			++$expected;

			if (null === self::sourceBundle($source)) {
				$failed[$source] = self::$bundle_failures[$source] ?? 'no reason recorded';
			} else {
				++$bundled;
			}
		}

		return ['bundled' => $bundled, 'expected' => $expected, 'failed' => $failed];
	}

	/**
	 * Forces the compiled bundles of the source directories on or off, or back to deciding (null).
	 *
	 * @internal for tests: bundles are otherwise used outside the console, in production
	 */
	public static function useBundles(?bool $enabled): void
	{
		self::$bundles         = $enabled;
		self::$source_bundles  = [];
		self::$bundle_failures = [];
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
	 * @param bool                $stateful true -> the stateful dir, false -> the source dir (app/settings/)
	 */
	public static function set(
		string $group,
		string $key,
		mixed $value,
		?ScopeInterface $scope = null,
		bool $stateful = true
	): void {
		self::assertNotLocked($group, $key);
		self::modify($group, static fn ($current) => $current->set($key, $value), $scope, $stateful);
	}

	/**
	 * Sets several keys of a setting group, the group's file written once.
	 *
	 * What `set()` does for each, without rendering and writing the file again per key: a tool
	 * writing hundreds of keys in one go (a catalog's scaffold) would otherwise write it hundreds
	 * of times.
	 *
	 * @param string               $group    the setting group name
	 * @param array<string, mixed> $values   the values, by key (a `.` path where nested)
	 * @param null|ScopeInterface  $scope    the scope to use (default: current app scope)
	 * @param bool                 $stateful true -> the stateful dir, false -> the source dir (app/settings/)
	 */
	public static function setMany(
		string $group,
		array $values,
		?ScopeInterface $scope = null,
		bool $stateful = true
	): void {
		if ([] === $values) {
			return;
		}

		foreach ($values as $key => $_) {
			self::assertNotLocked($group, (string) $key);
		}

		self::modify($group, static function ($current) use ($values): void {
			foreach ($values as $key => $value) {
				$current->set((string) $key, $value);
			}
		}, $scope, $stateful);
	}

	/**
	 * Unsets value of a given key in a setting group.
	 *
	 * @param string              $group    the setting group name
	 * @param string              $key      the setting key
	 * @param null|ScopeInterface $scope    the scope to use (default: current app scope)
	 * @param bool                $stateful true -> the stateful dir, false -> the source dir (app/settings/)
	 */
	public static function unset(
		string $group,
		string $key,
		?ScopeInterface $scope = null,
		bool $stateful = true
	): void {
		self::assertNotLocked($group, $key);
		self::modify($group, static fn ($current) => $current->remove($key), $scope, $stateful);
	}

	/**
	 * Checks if a given setting group exists.
	 * If a key is given, checks if the key exists in the setting group.
	 *
	 * @param string      $group the setting group
	 * @param null|string $key   the setting key name
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
	 */
	public static function applyMergeStrategy(array $a, array $b, array $strategies = []): array
	{
		if (\is_int(\key($a))) {
			return \array_merge($a, $b);
		}

		$merged = $a;

		foreach ($b as $key => $value) {
			$current  = $merged[$key] ?? null;
			$strategy = $strategies[$key] ?? self::MERGE_DEFAULT;

			if (self::MERGE_REPLACE === $strategy) {
				$merged[$key] = $value;

				continue;
			}

			if (self::MERGE_APPEND === $strategy && \is_array($current) && \is_array($value)) {
				$merged[$key] = self::isList($current) && self::isList($value)
					? \array_merge($current, $value)
					: self::applyMergeStrategy($current, $value);

				continue;
			}

			// The default: a map is merged key by key, so a source adds to what another declared, and a
			// **list is replaced whole**, being one value. `array_replace_recursive` spliced it element
			// by element instead, so a shorter list kept the tail of the longer one and no project could
			// restrict `OZ_2FA_CHANNEL_PRIORITY`.
			if (\is_array($current) && \is_array($value) && !self::isList($current) && !self::isList($value)) {
				$merged[$key] = self::applyMergeStrategy($current, $value);

				continue;
			}

			$merged[$key] = $value;
		}

		return $merged;
	}

	/**
	 * Generate settings export info usable in template file.
	 *
	 * @param string $group    the setting group name
	 * @param array  $settings the settings
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
	 * Whether an array is a list: no keys of its own, so it is one value rather than a map of them.
	 */
	private static function isList(array $value): bool
	{
		return [] === $value || \array_is_list($value);
	}

	/**
	 * Returns the settings group store instance.
	 *
	 * @param string $group  the setting group name
	 * @param bool   $reload reload settings from sources
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
	 * A locked setting is not edited at runtime either: what a source locked stays as it was declared.
	 *
	 * @throws RuntimeException when the key is locked
	 */
	private static function assertNotLocked(string $group, string $key): void
	{
		// The group must have been read for its locks to be known.
		self::requireGroupStore($group);

		if (isset(self::$locked[$group][$key])) {
			throw new RuntimeException(\sprintf(
				'Setting "%s.%s" is locked: it cannot be edited.',
				$group,
				$key
			));
		}
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

		// The file written may be a source of other scopes too (the application's), whose kept
		// settings would otherwise answer what it held before.
		foreach (self::$layers as $name => $layer) {
			unset(
				$layer['groups'][$group],
				$layer['values'][$group],
				$layer['strategies'][$group],
				$layer['locked'][$group]
			);

			self::$layers[$name] = $layer;
		}
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
			unset(
				self::$settings_groups[$group],
				self::$values[$group],
				self::$merge_strategies[$group],
				self::$locked[$group]
			);
		}

		if (\array_key_exists($group, self::$settings_groups)) {
			return;
		}

		// Read while it is being loaded, by its own file or by a group whose file this one's reads:
		// there is nothing to answer with, because the values do not exist yet and going on would run
		// the file again to find out what it is about to return ({@see self::$loading_groups}). A
		// project reads this and finds the cycle; what it replaced was a loop that ended in a killed
		// process.
		if (isset(self::$loading_groups[$group])) {
			throw new RuntimeException(\sprintf(
				'Settings group "%s" was read while it was being loaded: a settings file of that group,'
					. ' or of a group it reads, reads it back.',
				$group
			));
		}

		self::$loading_groups[$group] = true;

		try {
			$sources = self::getSources();
			$bundles = self::bundlesEnabled();

			foreach ($sources->getAllSources() as $source) {
				// A directory shipped with the code: its bundle holds every group it has.
				if ($bundles && !$sources->isStateful($source)) {
					$bundle = self::sourceBundle($source);

					if (null !== $bundle) {
						if (isset($bundle[$group])) {
							self::mergeGroup($group, $bundle[$group], $source);
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

					self::mergeGroup($group, $result, $abs_path);
				}
			}
		} finally {
			unset(self::$loading_groups[$group]);
		}
	}

	/**
	 * Merges a source's values of a group into what the sources before it gave.
	 */
	private static function mergeGroup(string $group, array $values, string $source = ''): void
	{
		$values = self::takeMergeStrategies($group, $values, $source);

		if (!\array_key_exists($group, self::$settings_groups)) {
			self::$settings_groups[$group] = new SettingsGroup($values);
		} else {
			self::$settings_groups[$group]->merge($values, self::$merge_strategies[$group] ?? []);
		}
	}

	/**
	 * Reads what a source declared about merging, and hands back its values without that declaration.
	 *
	 * A later source may **tighten** a strategy (lock a key nobody locked) and never loosen one: a
	 * plugin loaded after the application cannot unlock what the application locked, which is the whole
	 * point of locking an allow-list.
	 *
	 * @param array<string, mixed> $values
	 *
	 * @return array<string, mixed>
	 */
	private static function takeMergeStrategies(string $group, array $values, string $source): array
	{
		$declared = $values[self::MERGE_KEY] ?? null;

		unset($values[self::MERGE_KEY]);

		// A key already locked cannot be given a value again, whatever this source says about it.
		foreach (self::$locked[$group] ?? [] as $key => $_) {
			if (\array_key_exists($key, $values)) {
				throw new RuntimeException(\sprintf(
					'Setting "%s.%s" is locked: "%s" cannot change it.',
					$group,
					$key,
					'' === $source ? 'this source' : $source
				));
			}
		}

		if (!\is_array($declared)) {
			return $values;
		}

		foreach ($declared as $key => $strategy) {
			$known = [
				self::MERGE_DEFAULT,
				self::MERGE_REPLACE,
				self::MERGE_APPEND,
				self::MERGE_LOCK,
			];

			if (!\in_array($strategy, $known, true)) {
				throw new RuntimeException(\sprintf(
					'Unknown merge strategy "%s" for "%s.%s".',
					\get_debug_type($strategy),
					$group,
					$key
				));
			}

			if (isset(self::$locked[$group][$key]) && self::MERGE_LOCK !== $strategy) {
				throw new RuntimeException(\sprintf(
					'Setting "%s.%s" is locked: a later source cannot loosen it to "%s".',
					$group,
					$key,
					$strategy
				));
			}

			self::$merge_strategies[$group][$key] = $strategy;

			if (self::MERGE_LOCK === $strategy) {
				// Locked from the next source on: the one declaring it still gives the value.
				self::$locked[$group][$key] = true;
			}
		}

		return $values;
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

		// Being compiled further up the stack: a group file of it is running and has read a setting.
		// Answered as a directory without a bundle, which sends this read down the file-by-file
		// branch -- one file, bounded, and the right values. Not recorded as a failure: the compile
		// that is still running is the one that answers for this directory.
		if (isset(self::$compiling_sources[$dir])) {
			return null;
		}

		if (!\is_dir($dir)) {
			self::$bundle_failures[$dir] = 'not a directory';

			return null;
		}

		$root  = \rtrim(app()->getProjectDir()->getRoot(), '/\\');
		$cache = InstanceLayout::releaseBuildPath($root, InstanceLayout::BUILD_SETTINGS);
		$name  = \hash('xxh128', $dir);
		$file  = $cache . DS . $name . '.' . \hash('xxh128', \serialize([
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

		self::$compiling_sources[$dir] = true;

		try {
			$groups = self::compileBundle($dir);
		} finally {
			unset(self::$compiling_sources[$dir]);
		}

		self::$source_bundles[$dir] = $groups ?? false;

		if (null !== $groups) {
			self::writeBundle($cache, $name, $file, $groups);
		}

		return $groups;
	}

	/**
	 * Every group of a source directory: null when one cannot be bundled, with why in
	 * {@see self::$bundle_failures}.
	 *
	 * Reading a group runs its file, so anything can come out of it, and a directory this cannot
	 * bundle is read file by file rather than stopping a request: hence the catch-all. It used to
	 * discard what it caught, which read every failure as the one it was written for -- a group
	 * holding an object -- and lost the rest. What it loses is worst exactly when it matters: a
	 * diagnostic raised in here reaches the error handler, PHP does not re-enter that handler, so a
	 * failure of the handler's own (autoloading the logger with no descriptor left) is thrown from
	 * where the diagnostic was raised, which is inside this try.
	 *
	 * @return null|array<string, array>
	 */
	private static function compileBundle(string $dir): ?array
	{
		$groups = [];

		try {
			foreach (self::groupFiles($dir) as $group => $path) {
				$result = require $path;

				if (!\is_array($result)) {
					self::$bundle_failures[$dir] = \sprintf(
						'the group "%s" returned %s, not an array',
						$group,
						\get_debug_type($result)
					);

					return null;
				}

				if (!self::isExportable($result)) {
					self::$bundle_failures[$dir] = \sprintf(
						'the group "%s" holds an object no bundle could write back',
						$group
					);

					return null;
				}

				$groups[$group] = $result;
			}
		} catch (Exception $t) {
			// An exception and not every throwable: a group file of this directory failed, which is
			// what reading it file by file is the answer to. An `Error` is not that -- a stack
			// exhausted, a call to something that does not exist, a type that cannot hold what it
			// was given -- and reporting one as a directory that cannot be bundled is how a fault
			// came out as a release built one settings bundle short.
			self::$bundle_failures[$dir] = \sprintf('%s: %s', $t::class, $t->getMessage());

			return null;
		}

		unset(self::$bundle_failures[$dir]);

		\ksort($groups);

		return $groups;
	}

	/**
	 * The settings groups a source directory declares, each with its file: every `.php` file under
	 * it whose path is a group name.
	 *
	 * @return Generator<string, string>
	 */
	private static function groupFiles(string $dir): Generator
	{
		$root  = \rtrim($dir, '/\\') . DS;
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
		);

		foreach ($files as $file) {
			$path = $file->getPathname();

			if (!\str_ends_with($path, '.php')) {
				continue;
			}

			$group = \str_replace(DS, '/', \substr($path, \strlen($root), -4));

			if (\preg_match(self::REG_SETTING_GROUP_NAME, $group)) {
				yield $group => $path;
			}
		}
	}

	/**
	 * The settings directories a scope adds of its own, each saying whether it is stateful: none for
	 * the application, whose directories are every scope's.
	 *
	 * @return array<string, bool>
	 */
	private static function scopeSources(ScopeInterface $scope): array
	{
		if ($scope instanceof AppInterface) {
			return [];
		}

		$dirs   = [];
		$source = $scope->getSourcesDir()->getRoot() . DS . 'settings';

		if (\is_dir($source)) {
			$dirs[$source] = false;
		}

		$dirs[StateLayout::path($scope, StateLayout::SETTINGS)] = true;

		return $dirs;
	}

	/**
	 * Of the groups the directories declare, those of the shared ones or under them.
	 *
	 * @param list<string> $dirs
	 * @param list<string> $shared
	 *
	 * @return list<string>
	 */
	private static function sharedGroupsIn(array $dirs, array $shared): array
	{
		$found = [];

		foreach ($dirs as $dir) {
			if (!\is_dir($dir)) {
				continue;
			}

			foreach (self::groupFiles($dir) as $group => $_) {
				foreach ($shared as $name) {
					if ($group === $name || \str_starts_with($group, $name . '.')) {
						$found[$group] = true;

						break;
					}
				}
			}
		}

		return \array_keys($found);
	}

	/**
	 * A group's values, or null when no source declares it.
	 */
	private static function loadIfDeclared(string $group): ?array
	{
		self::checkSettingGroupName($group);
		self::loadAll($group);

		return isset(self::$settings_groups[$group]) ? self::load($group) : null;
	}

	/**
	 * The settings in use.
	 *
	 * @return SettingsLayer
	 */
	private static function layer(): array
	{
		return [
			'groups'     => self::$settings_groups,
			'strategies' => self::$merge_strategies,
			'locked'     => self::$locked,
			'values'     => self::$values,
			'loading'    => self::$loading_groups,
			'sources'    => self::getSources(),
		];
	}

	/**
	 * Puts settings in use.
	 *
	 * @param SettingsLayer $layer
	 */
	private static function useLayer(array $layer): void
	{
		self::$settings_groups  = $layer['groups'];
		self::$merge_strategies = $layer['strategies'];
		self::$locked           = $layer['locked'];
		self::$values           = $layer['values'];
		self::$loading_groups   = $layer['loading'];
		self::$sources          = $layer['sources'];
	}

	/**
	 * Whether var_export() writes a value back as it is: scalars, nulls, enum cases, arrays of them.
	 */
	private static function isExportable(mixed $value): bool
	{
		if (\is_array($value)) {
			return \array_all($value, self::isExportable(...));
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

			new FilesManager($cache)->writeAtomic(
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
