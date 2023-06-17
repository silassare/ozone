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

namespace OZONE\Core\FS\Drivers;

use OZONE\Core\FS\FilesManager;

/**
 * Class PublicLocalStorage.
 */
final class PublicLocalStorage extends AbstractLocalStorage
{
	/**
	 * {@inheritDoc}
	 */
	public static function get(): self
	{
		return new self();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function uploadsDir(): FilesManager
	{
		return app()
			->getPublicFilesDir()
			->cd('uploads' . DS, true);
	}
}
