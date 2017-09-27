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

	function ofv_gender(OFormValidator $ofv)
	{
		$gender = $ofv->getField('gender');

		if (!in_array($gender, OZoneSettings::get('oz.user', 'OZ_USER_ALLOWED_GENDERS'))) {
			$ofv->addError('OZ_FIELD_GENDER_INVALID');
		} else {
			$ofv->setField('gender', $gender);
		}
	}