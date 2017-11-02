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

	function ofv_birthdate(OFormValidator $ofv)
	{
		$birthdate = $ofv->getField('birth_date');
		$date  = OFormUtils::parseDate($birthdate);

		if ($date) {
			$year  = $date['year'];
			$month = $date['month'];
			$day   = $date['day'];

			$min_age = SettingsManager::get('oz.ofv.const', 'OZ_USER_MIN_AGE');
			$max_age = SettingsManager::get('oz.ofv.const', 'OZ_USER_MAX_AGE');

			if (OFormUtils::isBirthDate($month, $day, $year, $min_age, $max_age)) {
				$ofv->setField('birth_date', $day . '-' . $month . '-' . $year);

				return;
			}
		}

		$ofv->addError('OZ_FIELD_BIRTH_DATE_INVALID');
	}
