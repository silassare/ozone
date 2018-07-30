<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Ofv;

	function ofv_vpass(OFormValidator $ofv)
	{
		if (!OFormUtils::equalFields($ofv, 'pass', 'vpass')) {
			$ofv->addError('OZ_FIELD_PASS_AND_VPASS_NOT_EQUAL');
		}
	}