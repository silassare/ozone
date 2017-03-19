<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\FS;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	class OZoneFileSystem {

		/**
		 * @param string $pathname The
		 * @param int    $mode     The mode is 0777 by default, which means the widest possible access
		 *
		 * @throws \Exception
		 */
		public static function mkdir( $pathname, $mode = 0777 ) {

			$dir = dirname( $pathname );

			if ( !is_dir( $dir ) ) {
				if ( false === @mkdir( $dir, $mode, true ) AND !is_dir( $dir ) ) {
					throw new \Exception( "can't create directory: $dir" );
				}
			}
		}
	}