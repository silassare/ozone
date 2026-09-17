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

namespace OZONE\Core\Cli\Utils;

use JsonException;
use Throwable;

/**
 * Class Requirements.
 *
 * What a machine needs to run OZone, read from `composer.json` rather than from a hardcoded list:
 * the `install` script and `oz doctor check` would otherwise drift apart from the actual requirements.
 * When a project is loaded, its own `composer.json` is read too, so a project's extra extensions
 * are checked with the framework's.
 */
final class Requirements
{
	/**
	 * The PHP version constraint, e.g. `>=8.1`.
	 */
	public static function phpConstraint(): string
	{
		$require = self::require(self::ozoneComposerFile());

		return (string) ($require['php'] ?? '>=8.1');
	}

	/**
	 * The minimum PHP version the constraint asks for, e.g. `8.1`.
	 */
	public static function minPhpVersion(): string
	{
		\preg_match('~(\d+\.\d+(?:\.\d+)?)~', self::phpConstraint(), $m);

		return $m[1] ?? '8.1';
	}

	/**
	 * Whether the running PHP satisfies the minimum version.
	 */
	public static function phpVersionSatisfied(): bool
	{
		return \version_compare(\PHP_VERSION, self::minPhpVersion(), '>=');
	}

	/**
	 * The required extension names, without the `ext-` prefix.
	 *
	 * @return list<string> sorted, duplicates removed
	 */
	public static function extensions(): array
	{
		$names = [];

		foreach (self::composerFiles() as $file) {
			foreach (\array_keys(self::require($file)) as $package) {
				if (\str_starts_with($package, 'ext-')) {
					$names[] = \substr($package, 4);
				}
			}
		}

		$names = \array_values(\array_unique($names));

		\sort($names);

		return $names;
	}

	/**
	 * The required extensions that are not loaded.
	 *
	 * @return list<string>
	 */
	public static function missingExtensions(): array
	{
		return \array_values(\array_filter(
			self::extensions(),
			static fn (string $name): bool => !\extension_loaded($name)
		));
	}

	/**
	 * The `composer.json` files whose requirements apply: OZone's, and the project's when loaded.
	 *
	 * @return list<string>
	 */
	public static function composerFiles(): array
	{
		$files   = [self::ozoneComposerFile()];
		$project = OZ_PROJECT_DIR . 'composer.json';

		if (Utils::isProjectLoaded() && \is_file($project) && !\in_array($project, $files, true)) {
			$files[] = $project;
		}

		return $files;
	}

	/**
	 * OZone's own `composer.json`.
	 */
	public static function ozoneComposerFile(): string
	{
		return \dirname(OZ_OZONE_DIR) . DS . 'composer.json';
	}

	/**
	 * The `require` map of a `composer.json`, empty when it cannot be read.
	 *
	 * @return array<string, string>
	 */
	private static function require(string $file): array
	{
		static $cache = [];

		if (!isset($cache[$file])) {
			$cache[$file] = [];

			try {
				if (\is_file($file) && \is_readable($file)) {
					$data = \json_decode((string) \file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR);

					if (\is_array($data) && \is_array($data['require'] ?? null)) {
						/** @var array<string, string> $require */
						$require      = $data['require'];
						$cache[$file] = $require;
					}
				}
			} catch (JsonException|Throwable) {
				// An unreadable or malformed composer.json contributes nothing; doctor reports it.
			}
		}

		return $cache[$file];
	}
}
