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

	function ofv_date(OFormValidator $ofv)
	{
		$date = $ofv->getField('date');
		$date = OFormUtils::parseDate($date);

		// on verifie que la date de naissance est valide
		if ($date) {
			$year  = $date['year'];
			$month = $date['month'];
			$day   = $date['day'];

			$ofv->setField('date', $day . '-' . $month . '-' . $year);

			return;
		}

		// la date n'est pas valide
		$ofv->addError('OZ_FIELD_DATE_INVALID');
	}