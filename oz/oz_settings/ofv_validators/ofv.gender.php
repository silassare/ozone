<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Ofv;

use OZONE\OZ\Core\SettingsManager;

/**
 * @param \OZONE\OZ\Ofv\OFormValidator $ofv
 *
 * @throws \Exception
 */
function ofv_gender(OFormValidator $ofv)
{
	$gender = $ofv->getField('gender');

	if (!\in_array($gender, SettingsManager::get('oz.users', 'OZ_USER_ALLOWED_GENDERS'))) {
		$ofv->addError('OZ_FIELD_GENDER_INVALID');
	} else {
		$ofv->setField('gender', $gender);
	}
}
