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

namespace OZONE\Core\Cli\Deploy;

/**
 * Class ReleaseLayout.
 *
 * Where an atomic deployment puts things.
 *
 * ```
 * {root}/
 *   releases/20260912181500/   one checkout, never written to again once live
 *   current -> releases/...    the symlink the web server and the workers follow
 *   data/                      state, shared by every release and never inside one
 *   .ozone/                    caches, logs and build output, likewise
 *   .env                       the secrets, which are not in the repository
 * ```
 *
 * The state directories living **beside** the releases rather than inside one is the whole point:
 * a release is disposable, the state is not. Each release reaches them through symlinks, which is
 * also why nothing may store an absolute path -- a path captured in one release is wrong in the
 * next.
 */
final class ReleaseLayout
{
	public const RELEASES = 'releases';
	public const CURRENT  = 'current';

	/**
	 * The directories a release links to instead of holding.
	 *
	 * @return list<string>
	 */
	public static function sharedDirs(): array
	{
		return ['data', '.ozone'];
	}

	/**
	 * The files a release links to instead of holding.
	 *
	 * `.env` is deliberately not in the repository -- it holds the salt, the secret and the database
	 * password -- so a fresh checkout has none and cannot boot. It belongs beside the releases, put
	 * there once by the operator, and linked into each one.
	 *
	 * @return list<string>
	 */
	public static function sharedFiles(): array
	{
		return ['.env'];
	}

	/**
	 * The shared files a deploy root is missing.
	 *
	 * @return list<string>
	 */
	public static function missingSharedFiles(string $root): array
	{
		$missing = [];

		foreach (self::sharedFiles() as $file) {
			if (!\file_exists(\rtrim($root, DS) . DS . $file)) {
				$missing[] = $file;
			}
		}

		return $missing;
	}

	/**
	 * A release name from a time: sortable, and readable in an `ls`.
	 */
	public static function releaseName(?int $time = null): string
	{
		return \gmdate('YmdHis', $time ?? \time());
	}

	/**
	 * The releases directory of a deploy root.
	 */
	public static function releasesDir(string $root): string
	{
		return \rtrim($root, DS) . DS . self::RELEASES;
	}

	/**
	 * The path of one release.
	 */
	public static function releaseDir(string $root, string $name): string
	{
		return self::releasesDir($root) . DS . $name;
	}

	/**
	 * The `current` symlink of a deploy root.
	 */
	public static function currentLink(string $root): string
	{
		return \rtrim($root, DS) . DS . self::CURRENT;
	}

	/**
	 * The release `current` points at, or null when there is none yet.
	 */
	public static function currentRelease(string $root): ?string
	{
		$link = self::currentLink($root);

		if (!\is_link($link)) {
			return null;
		}

		$target = (string) \readlink($link);
		$name   = \basename($target);

		return '' === $name ? null : $name;
	}

	/**
	 * The releases of a deploy root, oldest first.
	 *
	 * @return list<string>
	 */
	public static function releases(string $root): array
	{
		$dir      = self::releasesDir($root);
		$releases = [];

		if (!\is_dir($dir)) {
			return $releases;
		}

		foreach (\scandir($dir) ?: [] as $entry) {
			if ('.' !== $entry && '..' !== $entry && \is_dir($dir . DS . $entry)) {
				$releases[] = $entry;
			}
		}

		\sort($releases);

		return $releases;
	}

	/**
	 * The release before the current one, which is what a rollback goes back to.
	 */
	public static function previousRelease(string $root): ?string
	{
		$releases = self::releases($root);
		$current  = self::currentRelease($root);

		if (null === $current) {
			return null;
		}

		$index = \array_search($current, $releases, true);

		if (false === $index || 0 === $index) {
			return null;
		}

		return $releases[$index - 1];
	}

	/**
	 * Whether a deploy root has the shape this expects.
	 */
	public static function isDeployRoot(string $root): bool
	{
		return \is_dir(self::releasesDir($root));
	}
}
