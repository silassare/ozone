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
	public const ROOT_SCOPE = 'root';

	/**
	 * Returns the scope name.
	 *
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Returns an instance of the files manager with the scope sources directory as root.
	 *
	 * This is where generated sources files should be stored.
	 *
	 * @return FilesManager
	 */
	public function getSourcesDir(): FilesManager;

	/**
	 * Returns an instance of the files manager with the scope private data directory as root.
	 *
	 * This directory should be protected from public access.
	 * This is where you should store private stateful data.
	 *
	 * @return FilesManager
	 */
	public function getDataDir(): FilesManager;

	/**
	 * Returns an instance of the files manager with the scope logs directory as root.
	 *
	 * @return FilesManager
	 */
	public function getLogsDir(): FilesManager;

	/**
	 * Returns an instance of the files manager with the scope cache directory as root.
	 *
	 * @return FilesManager
	 */
	public function getCacheDir(): FilesManager;

	/**
	 * Returns an instance of the files manager with the scope public directory as root.
	 *
	 * This directory is accessible from the web.
	 *
	 * @return FilesManager
	 */
	public function getPublicDir(): FilesManager;

	/**
	 * Returns an instance of the files manager with the scope settings directory as root.
	 *
	 * @return FilesManager
	 */
	public function getSettingsDir(): FilesManager;

	/**
	 * Returns an instance of the files manager with the scope templates directory as root.
	 *
	 * @return FilesManager
	 */
	public function getTemplatesDir(): FilesManager;

	/**
	 * Returns an instance of the files manager with the scope private files directory as root.
	 *
	 * This directory should be protected from public access.
	 * This is where you should store your private files.
	 *
	 * @return FilesManager
	 */
	public function getPrivateFilesDir(): FilesManager;

	/**
	 * Returns an instance of the files manager with the scope public files directory as root.
	 *
	 * This directory is accessible from the web.
	 * This is where public files should be stored.
	 *
	 * @return FilesManager
	 */
	public function getPublicFilesDir(): FilesManager;
}
