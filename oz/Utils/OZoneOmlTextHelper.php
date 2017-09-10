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

	final class OZoneOmlTextHelper
	{

		/**
		 * format a given data according to a given oml type
		 *
		 * @param string $type the oml text type
		 * @param mixed  $data the oml text data
		 *
		 * @return string
		 */
		public static function formatText($type, $data = null)
		{
			$text = '';
			switch ($type) {
				case 'file':
					$fid            = $data['fid'];
					$data['fthumb'] = (!!$data['fthumb'] AND file_exists($data['fthumb'])) ? 1 : 0;
					$data_str       = json_encode([
						'fid'    => $data['fid'],
						'fkey'   => $data['fkey'],
						'fclone' => $data['fclone'],
						'ftype'  => $data['ftype'],
						'fname'  => $data['fname'],
						'fthumb' => $data['fthumb']
					]);

					$data_str = base64_encode($data_str);
					$text     = "{" . $fid . ":" . $data_str . "}";
					break;
				case 'user':
					$text = "{uid:" . $data['uid'] . "}";
					break;
				case 'group':
					$text = "{gid:" . $data['gid'] . "}";
					break;
				case 'new_line':
					$text = "{o:nl}";
					break;
			}

			return $text;
		}
	}