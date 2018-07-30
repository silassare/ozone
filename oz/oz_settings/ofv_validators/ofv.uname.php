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

	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\Utils\StringUtils;

	function ofv_uname(OFormValidator $ofv)
	{
		$name = preg_replace(SettingsManager::get('oz.ofv.const', 'OZ_UNWANTED_CHAR_REG'), ' ', $ofv->getField('uname'));
		$name = trim($name);

		$contains_key_words = preg_match(SettingsManager::get('oz.ofv.const', 'OZ_EXCLUDE_KEY_WORDS'), $name);
		$e_msg              = null;

		if ($contains_key_words) {
			$e_msg = 'OZ_FIELD_USER_NAME_CONTAINS_KEYWORDS';
		} elseif (strlen($name) < SettingsManager::get('oz.ofv.const', 'OZ_USER_NAME_MIN_LENGTH')) {
			$e_msg = 'OZ_FIELD_USER_NAME_TOO_SHORT';
		} elseif (strlen($name) > SettingsManager::get('oz.ofv.const', 'OZ_USER_NAME_MAX_LENGTH')) {
			$e_msg = 'OZ_FIELD_USER_NAME_TOO_LONG';
		}

		if (is_null($e_msg)) {
			$ofv->setField('uname', StringUtils::clean($name));
		} else {
			$ofv->addError($e_msg);
		}
	}