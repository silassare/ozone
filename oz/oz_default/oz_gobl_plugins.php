<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ;

	use Gobl\CRUD\CRUD;
	use OZONE\OZ\Core\Assert;
	use OZONE\OZ\User\UsersUtils;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	CRUD::assertion("admin", function ()
	{
		Assert::assertIsAdmin();
		return true;
	});

	CRUD::assertion("user", function ()
	{
		Assert::assertUserVerified();
		return true;
	});

	CRUD::autoValue("user_id", [UsersUtils::class, "getCurrentUserId"]);