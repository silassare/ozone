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

namespace __PLH_NAMESPACE__;

use OZONE\Core\App\Context;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\Picture\AuthUserPictureImage;
use OZONE\Core\Auth\Picture\Enums\AuthUserPictureFallbackReason;
use OZONE\Core\Auth\Picture\Interfaces\AuthUserPictureFallbackInterface;

/**
 * A project's own fallback for a missing picture: a square of one color drawn by the server, as an
 * avatar of initials would be.
 */
final class AuthUserPictureInitials implements AuthUserPictureFallbackInterface
{
	public function fallback(?AuthUserInterface $owner, Context $context, AuthUserPictureFallbackReason $reason): ?AuthUserPictureImage
	{
		$image = \imagecreatetruecolor(120, 120);

		\imagefill($image, 0, 0, (int) \imagecolorallocate($image, 0, 128, 0));
		\ob_start();
		\imagepng($image);

		return AuthUserPictureImage::fromBytes((string) \ob_get_clean(), 'image/png');
	}
}
