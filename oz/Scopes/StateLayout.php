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

namespace OZONE\Core\Scopes;

use OZONE\Core\App\AbstractApp;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\FS\FS;
use OZONE\Core\Scopes\Interfaces\ScopeInterface;
use Throwable;
use WeakMap;

/**
 * Class StateLayout.
 *
 * Where a scope's state lives under `data/`.
 *
 * The rule is one directory per *kind*, then one per scope: `data/settings/api`, `data/files/root`,
 * `data/tmp-fs/api`, ... Kind first, because that is the level a backup rule, a container volume and
 * a retention policy are written at -- `data/` is what must be consistent across instances and
 * survive a restart, and `.ozone/` is per instance and may be deleted at any time.
 *
 * What must exist is `data/` itself: it is the volume a deployment mounts, and
 * {@see AbstractApp::getDataDir()} refuses to create it, so a node whose disk did
 * not come up says so instead of quietly starting on an empty one. The kind and scope directories
 * *inside* it are created on demand -- their absence costs nothing and cannot hide data loss, and a
 * plugin's directories are only knowable once the plugins have booted.
 */
final class StateLayout
{
	/**
	 * Stateful settings, written by `Settings::set()`.
	 */
	public const SETTINGS = 'settings';

	/**
	 * Private files, served only after an access check.
	 */
	public const PRIVATE_FILES = 'files';

	/**
	 * Public files, exposed through a symlink from the web root.
	 */
	public const PUBLIC_FILES = 'static';

	/**
	 * Temporary files: chunked uploads, and files a form has accepted but not yet persisted.
	 *
	 * Under `data/` and not under `.ozone/cache/`, because losing it loses a user's work in
	 * progress. It is collected by `TempFS`, never by an operator.
	 */
	public const TEMP = 'tmp-fs';

	/**
	 * Durable key-value state, for a store that has no database or Redis behind it.
	 */
	public const STATE = 'state';

	/**
	 * The name of the symlink a web server follows to reach a scope's public files.
	 */
	public const PUBLIC_LINK = 'static';

	/**
	 * Every kind, in the order the scaffolding creates them.
	 *
	 * @return list<string>
	 */
	public static function kinds(): array
	{
		return [self::SETTINGS, self::PRIVATE_FILES, self::PUBLIC_FILES, self::TEMP, self::STATE];
	}

	/**
	 * The directory of a kind for a scope: `data/{kind}/{scope}`.
	 *
	 * Created when missing; what is not created is `data/` itself, so the error below is always about
	 * the volume rather than about a directory nobody got round to making.
	 *
	 * @param ScopeInterface $scope
	 * @param string         $kind  one of the constants of this class
	 *
	 * @return FilesManager
	 *
	 * @throws RuntimeException when `data/` is missing
	 */
	public static function dir(ScopeInterface $scope, string $kind): FilesManager
	{
		try {
			// The scope's own data root, never app(): a scope resolves its directories from its
			// constructor, before OZone::bootstrap() has an app to hand out.
			return $scope->getDataDir()->cd($kind . DS . $scope->getStateSlug(), true);
		} catch (Throwable $t) {
			throw new RuntimeException(\sprintf(
				'Unable to reach the state directory "data%s%s%s%s" of scope "%s".'
					. ' The "data" directory is never created automatically: check that the volume is'
					. ' mounted, and run "oz project link" on a new release.',
				DS,
				$kind,
				DS,
				$scope->getStateSlug(),
				$scope->getName()
			), null, $t);
		}
	}

	/**
	 * The path of {@see dir()}, kept for the life of the scope object.
	 *
	 * What a request reads on its way (the stateful settings sources, the route table key) asks for it
	 * again and again, and building the directory's FilesManager each time cost more than the rest of
	 * reading them. It is still checked to exist on every call (one stat), and created again when
	 * missing.
	 *
	 * @param ScopeInterface $scope
	 * @param string         $kind  one of the constants of this class
	 *
	 * @throws RuntimeException when `data/` is missing
	 */
	public static function path(ScopeInterface $scope, string $kind): string
	{
		/** @var null|WeakMap<ScopeInterface, array<string, string>> $paths */
		static $paths = null;

		$paths ??= new WeakMap();

		$path = $paths[$scope][$kind] ?? null;

		if (null === $path || !\is_dir($path)) {
			$path  = self::dir($scope, $kind)->getRoot();
			$known = $paths[$scope] ?? [];

			$known[$kind]  = $path;
			$paths[$scope] = $known;
		}

		return $path;
	}

	/**
	 * The directory of a kind for a scope slug, under a project directory.
	 *
	 * The path-based form, for the scaffolding commands: they run before there is an app to ask.
	 *
	 * @param FilesManager $project the project directory
	 * @param string       $kind    one of the constants of this class
	 * @param string       $slug    the scope slug, {@see ScopeInterface::getStateSlug()}
	 * @param bool         $create  whether to create it
	 *
	 * @return FilesManager
	 */
	public static function dirAt(
		FilesManager $project,
		string $kind,
		string $slug,
		bool $create = false
	): FilesManager {
		// A fresh manager, never the caller's: cd() moves a FilesManager in place, so walking the
		// one that was passed in would leave the caller pointing at data/{kind}/{slug}.
		return FS::from($project->getRoot())
			->cd('data', $create)
			->cd($kind . DS . $slug, $create);
	}

	/**
	 * The path of that symlink: `{document root}/static`.
	 */
	public static function publicLinkPath(ScopeInterface $scope): string
	{
		return $scope->getDocumentRootDir()->resolve(self::PUBLIC_LINK);
	}

	/**
	 * Creates, or repairs, the symlink from a scope's document root to its public files.
	 *
	 * Idempotent, and never destructive: a real directory in the way is reported rather than
	 * replaced, because it may hold files nothing else has a copy of.
	 *
	 * PHP itself never depends on this link -- {@see ScopeInterface::getPublicFilesDir()} resolves
	 * the real path under `data/`, so uploads work without it. Only the web server follows it, which
	 * is why it is created here and by the scaffolding rather than at runtime.
	 *
	 * @return string one of `created`, `ok`, `replaced`, or `blocked`
	 */
	public static function link(ScopeInterface $scope): string
	{
		return self::linkTo(self::dir($scope, self::PUBLIC_FILES)->getRoot(), self::publicLinkPath($scope));
	}

	/**
	 * The path-based form of {@see self::link()}, for the scaffolding commands.
	 *
	 * @param FilesManager $project       the project directory
	 * @param string       $slug          the scope slug
	 * @param string       $document_root the directory the web server serves for that scope
	 *
	 * @return string one of `created`, `ok`, `replaced`, or `blocked`
	 */
	public static function linkAt(FilesManager $project, string $slug, string $document_root): string
	{
		return self::linkTo(
			self::dirAt($project, self::PUBLIC_FILES, $slug, true)->getRoot(),
			\rtrim($document_root, DS) . DS . self::PUBLIC_LINK
		);
	}

	/**
	 * Creates every state directory of a scope.
	 *
	 * Called by `oz project create`, `oz scopes add` and `oz project link`, never by a request.
	 *
	 * @param ScopeInterface $scope
	 *
	 * @return list<string> the absolute paths, in {@see self::kinds()} order
	 */
	public static function ensure(ScopeInterface $scope): array
	{
		$created = [];

		foreach (self::kinds() as $kind) {
			$created[] = self::dir($scope, $kind)->getRoot();
		}

		return $created;
	}

	/**
	 * The path-based form of {@see self::ensure()}, for the scaffolding commands.
	 *
	 * @param FilesManager $project the project directory
	 * @param string       $slug    the scope slug
	 *
	 * @return list<string> the absolute paths, in {@see self::kinds()} order
	 */
	public static function ensureAt(FilesManager $project, string $slug): array
	{
		$created = [];

		foreach (self::kinds() as $kind) {
			$created[] = self::dirAt($project, $kind, $slug, true)->getRoot();
		}

		return $created;
	}

	/**
	 * Points a symlink at a directory, idempotently and without ever deleting real content.
	 *
	 * @return string one of `created`, `ok`, `replaced`, or `blocked`
	 */
	private static function linkTo(string $target, string $path): string
	{
		// The document root may not exist yet when a project is scaffolded: symlink() would fail
		// silently and leave no link at all.
		$parent = \dirname($path);

		if (!\is_dir($parent) && !@\mkdir($parent, 0o775, true) && !\is_dir($parent)) {
			return 'blocked';
		}

		if (\is_link($path)) {
			if (\realpath((string) \readlink($path)) === \realpath($target)) {
				return 'ok';
			}

			\unlink($path);
			\symlink($target, $path);

			return 'replaced';
		}

		if (\file_exists($path)) {
			// Whatever this is, it is not ours to delete.
			return 'blocked';
		}

		\symlink($target, $path);

		return 'created';
	}
}
