<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Utils;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class OMLTextHelper
	{
		const OML_NEW_LINE = 1;
		const OML_FILE = 2;

		/**
		 * format a given data according to a given oml type
		 *
		 * @param string $type the oml text type
		 * @param mixed  $data the oml text data
		 *
		 * @return string
		 * @throws \Exception
		 */
		public static function formatText($type, $data = null)
		{
			throw new \Exception('Silo replace oml with otpl.');
			$text = '';
			switch ($type) {
				case self::OML_FILE :
					break;
				case self::OML_NEW_LINE:
					$text = "{o:nl}";
					break;
			}

			return $text;
		}
	}