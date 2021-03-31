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
function ofv_email(OFormValidator $ofv)
{
	$email = $ofv->getField('email');
	$rules = $ofv->getRules('email');

	if (!\filter_var($email, \FILTER_VALIDATE_EMAIL)) {
		$ofv->addError('OZ_FIELD_EMAIL_INVALID');
	} elseif (\in_array('not-registered', $rules) && UsersManager::searchUserWithEmail($email)) {
		$ofv->addError('OZ_FIELD_EMAIL_ALREADY_REGISTERED', ['email' => $email]);
	} elseif (\in_array('registered', $rules) && !UsersManager::searchUserWithEmail($email)) {
		$ofv->addError('OZ_FIELD_EMAIL_NOT_REGISTERED');
	} else {
		$ofv->setField('email', $email);
	}
}
