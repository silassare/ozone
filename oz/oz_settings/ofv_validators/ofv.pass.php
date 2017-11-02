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

	function ofv_pass(OFormValidator $ofv)
	{
		$pass = $ofv->getField('pass');

		$len = strlen($pass);
		if ($len < SettingsManager::get('oz.ofv.const', 'OZ_PASS_MIN_LENGTH')) {
			$ofv->addError('OZ_FIELD_PASS_TOO_SHORT');
		} elseif ($len > SettingsManager::get('oz.ofv.const', 'OZ_PASS_MAX_LENGTH')) {
			$ofv->addError('OZ_FIELD_PASS_TOO_LONG');
		} else {
			$ofv->setField('pass', $pass);
		}
	}