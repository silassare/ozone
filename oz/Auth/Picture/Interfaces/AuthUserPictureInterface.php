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

namespace OZONE\Core\Auth\Picture\Interfaces;

use OZONE\Core\Db\OZFile;

/**
 * Interface AuthUserPictureInterface.
 *
 * An auth user with a picture `oz:auth-user-picture` may serve. Every entity of a table made by
 * `AuthUsersRepository::makeAuthUserTable()` has it (its `pic` column); an auth user type of a
 * project's own repository implements it to have a picture, and is answered as having none
 * otherwise.
 */
interface AuthUserPictureInterface
{
	/**
	 * The auth user's picture file, or null when it has none (or only an upload not yet kept).
	 */
	public function getAuthUserPicture(): ?OZFile;
}
