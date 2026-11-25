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

use OZONE\Core\App\Context;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;

/**
 * Who may see an auth user's picture, of any type, through `oz:auth-user-picture`. A project names its own in `oz.auth.users`
 * `OZ_AUTH_USER_PICTURE_ACCESS` (members of the same tenant, contacts, ...).
 */
interface AuthUserPictureAccessInterface
{
	/**
	 * Whether the request may see the picture of `$owner`.
	 */
	public function allows(AuthUserInterface $owner, Context $context): bool;
}
