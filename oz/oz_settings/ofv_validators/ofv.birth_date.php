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

	use OZONE\OZ\Core\SettingsManager;

	function ofv_birth_date(OFormValidator $ofv)
	{
		$birth_date = $ofv->getField('birth_date');
		$min_age    = SettingsManager::get('oz.ofv.const', 'OZ_USER_MIN_AGE');
		$max_age    = SettingsManager::get('oz.ofv.const', 'OZ_USER_MAX_AGE');

		if (!OFormUtils::isBirthDate($birth_date, $min_age, $max_age)) {
			$ofv->addError('OZ_FIELD_BIRTH_DATE_INVALID');

			return;
		}

		$format     = OFormUtils::parseDate($birth_date);
		$birth_date = $format["DD-MM-YYYY"];

		$ofv->setField('birth_date', $birth_date);
	}
