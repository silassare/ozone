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

	function ofv_birthdate(OFormValidator $ofv)
	{
		$bdate = $ofv->getField('bdate');
		$date  = OFormUtils::parseDate($bdate);

		// on verifie que la date de naissance est valide
		if ($date) {
			$year  = $date['year'];
			$month = $date['month'];
			$day   = $date['day'];

			$min_age = OZoneSettings::get('oz.ofv.const', 'OZ_USER_MIN_AGE');
			$max_age = OZoneSettings::get('oz.ofv.const', 'OZ_USER_MAX_AGE');

			if (OFormUtils::isBirthDate($month, $day, $year, $min_age, $max_age)) {
				$ofv->setField('bdate', $day . '-' . $month . '-' . $year);

				return;
			}
		}

		// la date de naissance n'est pas valide
		$ofv->addError('OZ_FIELD_BIRTHDATE_INVALID');
	}
