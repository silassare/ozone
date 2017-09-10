<?php

	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Ofv;

	use OZONE\OZ\Core\OZoneSettings;
	use OZONE\OZ\User\OZoneUserUtils;

	function ofv_uid(OFormValidator $ofv)
	{
		$uid   = $ofv->getField('uid');
		$rules = $ofv->getRules('uid');
		$safe  = false;

		if (preg_match(OZoneSettings::get('oz.user', 'OZ_USER_UID_REG'), $uid)) {
			$validOnly = in_array('valid-user-only', $rules);
			$result    = OZoneUserUtils::getUserObject($uid)
									   ->getUsersListData([$uid], $validOnly);
			$safe      = isset($result[$uid]);
		}

		if (!$safe) {
			$ofv->addError('OZ_USER_ID_INVALID');
		}
	}
