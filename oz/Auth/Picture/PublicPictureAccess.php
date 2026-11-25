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

namespace OZONE\Core\Auth\Picture;

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\Picture\Interfaces\AuthUserPictureAccessInterface;

/**
 * Anyone, signed in or not.
 */
final class PublicPictureAccess implements AuthUserPictureAccessInterface
{
	#[Override]
	public function allows(AuthUserInterface $owner, Context $context): bool
	{
		return true;
	}
}
