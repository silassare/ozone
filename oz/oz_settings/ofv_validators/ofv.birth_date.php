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
function ofv_birth_date(OFormValidator $ofv)
{
	$birth_date = $ofv->getField('birth_date');
	$min_age    = SettingsManager::get('oz.ofv.const', 'OZ_USER_MIN_AGE');
	$max_age    = SettingsManager::get('oz.ofv.const', 'OZ_USER_MAX_AGE');

	if (!OFormUtils::isBirthDate($birth_date, $min_age, $max_age)) {
		$ofv->addError('OZ_FIELD_BIRTH_DATE_INVALID', ['input' => $birth_date, 'min' => $min_age, 'max' => $max_age]);

		return;
	}

	$format     = OFormUtils::parseDate($birth_date);
	$birth_date = $format['YYYY-MM-DD'];

	$ofv->setField('birth_date', $birth_date);
}
