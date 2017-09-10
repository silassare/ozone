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
	use OZONE\OZ\Utils\OZoneStr;

	function ofv_custom_string_validator(OFormValidator $ofv, $field, $regex, array $key_words, $on_fails, $on_too_short, $on_too_long)
	{
		$name = preg_replace(OZoneSettings::get('oz.ofv.const', 'OZ_UNWANTED_CHAR_REG'), ' ', $ofv->getField($field));
		$name = trim($name);

		$contains_key_words = preg_match(OZoneSettings::get('oz.ofv.const', 'OZ_EXCLUDE_KEY_WORDS'), $name);

		// on verifie le name
		if (!$contains_key_words AND preg_match(OZoneSettings::get('oz.ofv.const', 'SX_NAME_REG'), $name)) {
			$ofv->setField('$field', OZoneStr::clean($name));
		} else {
			// le name n'est pas valide
			$e_msg = 'SX_NAME_INVALID';

			if ($contains_key_words) {
				$e_msg = 'SX_NAME_CONTAINS_KEYWORDS';
			} elseif (strlen($name) < OZoneSettings::get('oz.ofv.const', 'SX_NAME_MIN_LENGTH')) {
				$e_msg = 'SX_NAME_TOO_SHORT';
			} elseif (strlen($name) > OZoneSettings::get('oz.ofv.const', 'SX_NAME_MAX_LENGTH')) {
				$e_msg = 'SX_NAME_TOO_LONG';
			}

			$ofv->addError($e_msg);
		}
	}