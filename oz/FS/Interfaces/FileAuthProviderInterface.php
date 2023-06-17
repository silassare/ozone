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

namespace OZONE\Core\FS\Interfaces;

use OZONE\Core\Auth\Interfaces\AuthProviderInterface;
use OZONE\Core\Db\OZFile;

/**
 * Interface FileAuthProviderInterface.
 */
interface FileAuthProviderInterface extends AuthProviderInterface
{
	/**
	 * Gets the file.
	 *
	 * @return \OZONE\Core\Db\OZFile
	 */
	public function getFile(): OZFile;
}
