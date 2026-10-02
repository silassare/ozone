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

namespace OZONE\Core\FS\Images;

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\Db\OZFile;
use OZONE\Core\FS\Images\Interfaces\ImageOriginalAccessInterface;
use OZONE\Core\Roles\Roles;

/**
 * The default: the user who uploaded the image, and administrators.
 */
final class UploaderOrAdminOriginalAccess implements ImageOriginalAccessInterface
{
	#[Override]
	public function allows(OZFile $file, Context $context): bool
	{
		if (!$context->hasAuthenticatedUser()) {
			return false;
		}

		$user = $context->auth()->user();

		return (
			$file->getUploaderType() === $user->getAuthUserType()
			&& (string) $file->getUploaderID() === $user->getAuthIdentifier()
		) || Roles::isAdmin($user, false);
	}
}
