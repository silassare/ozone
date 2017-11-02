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

	use OZONE\OZ\User\UsersUtils;
	use OZONE\OZ\Utils\StringUtils;

	function ofv_email(OFormValidator $ofv)
	{
		$email = $ofv->getField('email');
		$rules = $ofv->getRules('email');

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$ofv->addError('OZ_FIELD_EMAIL_INVALID');
		} elseif (in_array('not-registered', $rules) AND UsersUtils::searchUserWithEmail($email)) {
			$ofv->addError('OZ_FIELD_EMAIL_ALREADY_REGISTERED', ['email' => $email]);
		} elseif (in_array('registered', $rules) AND !UsersUtils::searchUserWithEmail($email)) {
			$ofv->addError('OZ_FIELD_EMAIL_NOT_REGISTERED');
		} else {
			$ofv->setField('email', StringUtils::clean($email));
		}
	}