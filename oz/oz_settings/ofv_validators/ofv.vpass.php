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
	function ofv_vpass(OFormValidator $ofv)
	{
		if (!OFormUtils::equalFields($ofv, 'pass', 'vpass')) {
			$ofv->addError('OZ_FIELD_PASS_AND_VPASS_NOT_EQUAL');
		}
	}