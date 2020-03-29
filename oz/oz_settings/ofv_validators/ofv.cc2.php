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

use OZONE\OZ\User\UsersManager;

/**
 * @param \OZONE\OZ\Ofv\OFormValidator $ofv
 *
 * @throws \Exception
 */
function ofv_cc2(OFormValidator $ofv)
{
	$cc2   = \strtoupper($ofv->getField('cc2')); // <-- important
	$rules = $ofv->getRules('cc2');

	if (\in_array('authorized-only', $rules)) {
		if (!UsersManager::authorizedCountry($cc2)) {
			$ofv->addError('OZ_FIELD_COUNTRY_NOT_ALLOWED');

			return;
		}
	} elseif (!UsersManager::getCountryObject($cc2)) {
		$ofv->addError('OZ_FIELD_COUNTRY_UNKNOWN');

		return;
	}

	$ofv->setField('cc2', $cc2);
}
