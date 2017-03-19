<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\App;

	use OZONE\OZ\Exceptions\OZoneBaseException;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	interface AppInterface {
		/**
		 * Is called before the requested service is executed.
		 *
		 * @return void
		 */
		public static function onInit();

		/**
		 * Is called when some unhandled ozone error occurs.
		 *
		 * @param \OZONE\OZ\Exceptions\OZoneBaseException $err The ozone error object.
		 *
		 * @return bool  Returns True for cancel error, False for log error and exit.
		 */
		public static function onError( OZoneBaseException $err );
	}