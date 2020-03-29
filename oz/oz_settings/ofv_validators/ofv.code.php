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

use OZONE\OZ\Core\SettingsManager;

/**
 * @param \OZONE\OZ\Ofv\OFormValidator $ofv
 *
 * @throws \Exception
 */
function ofv_code(OFormValidator $ofv)
{
	$code = $ofv->getField('code');

	if (\preg_match(SettingsManager::get('oz.ofv.const', 'OZ_CODE_REG'), $code)) {
		$ofv->setField('code', $code);
	} else {
		$ofv->addError('OZ_AUTH_CODE_INVALID');
	}
}
