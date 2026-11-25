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

namespace OZONE\Core\Auth\Picture\Enums;

/**
 * Why `oz:auth-user-picture` answers without the user's picture: the keys of `OZ_AUTH_USER_PICTURE_FALLBACK`.
 */
enum AuthUserPictureFallbackReason: string
{
	/** The rule refuses the viewer, or no user has that id. */
	case REFUSED = 'refused';

	/** The viewer is allowed and the user has no picture to serve. */
	case MISSING = 'missing';
}
