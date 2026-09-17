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

namespace OZONE\Core\Testing;

use Override;
use OZONE\Core\App\AbstractApp;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\FS\FS;

/**
 * Class SandboxApp.
 *
 * The app of a test suite. Given a sandbox directory ({@see Sandbox}), it uses it as its project
 * directory, so tests neither read state from nor leave it in the repository they run from.
 */
class SandboxApp extends AbstractApp
{
	/**
	 * @param null|string $sandbox the project directory to use, or null for the working directory
	 */
	public function __construct(private readonly ?string $sandbox = null)
	{
		parent::__construct();
	}

	#[Override]
	public function getProjectDir(): FilesManager
	{
		return null === $this->sandbox ? parent::getProjectDir() : FS::from($this->sandbox);
	}

	#[Override]
	public function getSourcesDir(): FilesManager
	{
		return null === $this->sandbox ? parent::getSourcesDir() : FS::from($this->sandbox)->cd('app', true);
	}
}
