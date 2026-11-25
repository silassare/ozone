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
use OZONE\Core\Db\OZUser;
use OZONE\Core\Users\Picture\Interfaces\UserPictureAccessInterface;

/**
 * A project's own picture rule: signed-in users whose email is at the owner's domain, the owner's
 * colleagues.
 */
final class UserPictureFriendsAccess implements UserPictureAccessInterface
{
	public function allows(OZUser $owner, Context $context): bool
	{
		if (!$context->hasAuthenticatedUser()) {
			return false;
		}

		$viewer = $context->auth()->user()->getAuthIdentifiers()['email'] ?? '';
		$domain = \strrchr((string) $owner->getEmail(), '@');

		return false !== $domain && \is_string($viewer) && \str_ends_with($viewer, $domain);
	}
}
