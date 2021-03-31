<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Ofv;

use OZONE\OZ\User\UsersManager;

/**
 * @param \OZONE\OZ\Ofv\OFormValidator $ofv
 *
 * @throws \Exception
 */
function ofv_phone(OFormValidator $ofv)
{
	$phone = $ofv->getField('phone');
	$rules = $ofv->getRules('phone');
	$phone = \str_replace(' ', '', $phone);

	if (!\preg_match('~^\+\d{6,15}$~', $phone)) {
		$ofv->addError('OZ_FIELD_PHONE_INVALID');
	} elseif (\in_array('not-registered', $rules) && UsersManager::searchUserWithPhone($phone)) {
		$ofv->addError('OZ_FIELD_PHONE_ALREADY_REGISTERED', ['phone' => $phone]);
	} elseif (\in_array('registered', $rules) && !UsersManager::searchUserWithPhone($phone)) {
		$ofv->addError('OZ_FIELD_PHONE_NOT_REGISTERED');
	} else {
		$ofv->setField('phone', $phone);
	}
}
