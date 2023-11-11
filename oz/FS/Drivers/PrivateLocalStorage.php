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

use OZONE\Core\App\Context;
use OZONE\Core\Db\OZFile;
use OZONE\Core\Exceptions\UnauthorizedActionException;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\Http\Uri;

/**
 * Class PrivateLocalStorage.
 */
final class PrivateLocalStorage extends AbstractLocalStorage
{
	/**
	 * {@inheritDoc}
	 */
	public static function get(string $name): self
	{
		return new self($name);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \OZONE\Core\Exceptions\UnauthorizedActionException
	 */
	public function publicUri(Context $context, OZFile $file): Uri
	{
		throw new UnauthorizedActionException('Private files are not accessible publicly.');
	}

	/**
	 * {@inheritDoc}
	 */
	protected function uploadsDir(): FilesManager
	{
		return app()
			->getPrivateFilesDir()
			->cd('uploads' . DS, true);
	}
}
