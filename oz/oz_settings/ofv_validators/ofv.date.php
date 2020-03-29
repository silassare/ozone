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

/**
 * @param \OZONE\OZ\Ofv\OFormValidator $ofv
 *
 * @throws \Exception
 */
function ofv_date(OFormValidator $ofv)
{
	$date   = $ofv->getField('date');
	$format = OFormUtils::parseDate($date);

	if (!$format) {
		$ofv->addError('OZ_FIELD_DATE_INVALID');

		return;
	}

	$ofv->setField('date', $format['YYYY-MM-DD']);
}
