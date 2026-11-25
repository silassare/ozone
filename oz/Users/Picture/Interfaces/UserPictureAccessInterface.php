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

namespace OZONE\Core\Users\Picture\Interfaces;

use OZONE\Core\App\Context;
use OZONE\Core\Db\OZUser;

/**
 * Who may see a user's picture through `oz:user-picture`. A project names its own in `oz.users`
 * `OZ_USER_PICTURE_ACCESS` (members of the same tenant, contacts, ...).
 */
interface UserPictureAccessInterface
{
	/**
	 * Whether the request may see the picture of `$owner`.
	 */
	public function allows(OZUser $owner, Context $context): bool;
}
