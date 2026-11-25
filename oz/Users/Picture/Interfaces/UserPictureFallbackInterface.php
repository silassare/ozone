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
use OZONE\Core\Users\Picture\Enums\UserPictureFallbackReason;
use OZONE\Core\Users\Picture\UserPictureImage;

/**
 * What `oz:user-picture` answers in place of a picture: named in `oz.users`
 * `OZ_USER_PICTURE_FALLBACK`, for a viewer the rule refuses, for a user with no picture, or both.
 * The image it gives is rendered as a picture is, with the forced tokens and the ones asked for.
 */
interface UserPictureFallbackInterface
{
	/**
	 * The image to answer with, or null for the status (403 refused, 404 missing).
	 *
	 * @param null|OZUser  the user asked for; null when no user has that id, which a refused
	 *                           viewer is told no differently than about a user it may not see
	 */
	public function fallback(?OZUser $owner, Context $context, UserPictureFallbackReason $reason): ?UserPictureImage;
}
