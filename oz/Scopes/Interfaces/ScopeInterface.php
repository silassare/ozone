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

namespace OZONE\Core\Scopes\Interfaces;

use OZONE\Core\FS\FilesManager;

/**
 * Interface ScopeInterface.
 */
interface ScopeInterface
{
	/**
	 * The root scope name.
	 */
	public const string ROOT_SCOPE = 'root';

	/**
	 * Returns the scope name.
	 */
	public function getName(): string;

	/**
	 * Returns an instance of the files manager with the scope sources directory as root.
	 *
	 * This is where generated sources files should be stored.
	 */
	public function getSourcesDir(): FilesManager;

	/**
	 * Returns an instance of the files manager with the scope private data directory as root.
	 *
	 * This directory should be protected from public access.
	 * This is where you should store private stateful data.
	 */
	public function getDataDir(): FilesManager;

	/**
	 * Returns an instance of the files manager with the scope logs directory as root.
	 */
	public function getLogsDir(): FilesManager;

	/**
	 * Returns an instance of the files manager with the scope cache directory as root.
	 */
	public function getCacheDir(): FilesManager;

	/**
	 * Returns an instance of the files manager with the scope public directory as root.
	 *
	 * This directory is accessible from the web.
	 */
	public function getPublicDir(): FilesManager;

	/**
	 * Returns an instance of the files manager with the scope settings directory as root.
	 */
	public function getSettingsDir(): FilesManager;

	/**
	 * Returns an instance of the files manager with the scope stateful settings directory as root.
	 */
	public function getStatefulSettingsDir(): FilesManager;

	/**
	 * The scope's own directory under `data/`, which holds one directory per state kind.
	 *
	 * `root` for the root scope, the scope name for a sub-scope, `plugins/{name}` for a plugin.
	 */
	public function getStateSlug(): string;

	/**
	 * Gets the scope temporary files directory (`data/{scope}/tmp-fs`).
	 *
	 * Under `data/` and not `.ozone/cache/`: an in-flight chunked upload, or a file a form has
	 * accepted, is a user's work in progress and not a cache.
	 */
	public function getTempDir(): FilesManager;

	/**
	 * Gets the scope durable key-value state directory (`data/{scope}/state`).
	 *
	 * For a store whose loss is visible to a user and that has no database or Redis behind it.
	 */
	public function getStateStoreDir(): FilesManager;

	/**
	 * Gets the directory the web server serves for this scope.
	 *
	 * Not to be confused with {@see self::getPublicFilesDir()}, which is where public *files* are
	 * stored: the document root only contains the entry point and a symlink to that directory.
	 */
	public function getDocumentRootDir(): FilesManager;

	/**
	 * Returns an instance of the files manager with the scope templates directory as root.
	 */
	public function getTemplatesDir(): FilesManager;

	/**
	 * Returns an instance of the files manager with the scope private files directory as root.
	 *
	 * This directory should be protected from public access.
	 * This is where you should store your private files.
	 */
	public function getPrivateFilesDir(): FilesManager;

	/**
	 * Returns an instance of the files manager with the scope public files directory as root.
	 *
	 * This directory is accessible from the web.
	 * This is where public files should be stored.
	 */
	public function getPublicFilesDir(): FilesManager;
}
