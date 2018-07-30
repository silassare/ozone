<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Ofv;

	use OZONE\OZ\User\UsersUtils;

	function ofv_phone(OFormValidator $ofv)
	{
		$phone = $ofv->getField('phone');
		$rules = $ofv->getRules('phone');
		$phone = str_replace(' ', '', $phone);

		if (!preg_match('#^\+\d{6,15}$#', $phone)) {
			$ofv->addError('OZ_FIELD_PHONE_INVALID');
		} elseif (in_array('not-registered', $rules) AND UsersUtils::searchUserWithPhone($phone)) {
			$ofv->addError('OZ_FIELD_PHONE_ALREADY_REGISTERED', ['phone' => $phone]);
		} elseif (in_array('registered', $rules) AND !UsersUtils::searchUserWithPhone($phone)) {
			$ofv->addError('OZ_FIELD_PHONE_NOT_REGISTERED');
		} else {
			$ofv->setField('phone', $phone);
		}
	}
