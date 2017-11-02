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

	function ofv_cc2(OFormValidator $ofv)
	{
		$cc2   = strtoupper($ofv->getField('cc2')); // <- important
		$rules = $ofv->getRules('cc2');

		if (in_array('authorized-only', $rules)) {
			if (!UsersUtils::authorizedCountry($cc2)) {
				$ofv->addError('OZ_FIELD_COUNTRY_NOT_ALLOWED');

				return;
			}
		} elseif (!UsersUtils::getCountryObject($cc2)) {
			$ofv->addError('OZ_FIELD_COUNTRY_UNKNOWN');

			return;
		}

		$ofv->setField('cc2', $cc2);
	}