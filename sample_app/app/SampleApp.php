<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\App;

	use OZONE\OZ\Exceptions\OZoneBaseException;
	use OZONE\OZ\App\AppInterface;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class SampleApp implements AppInterface {

		/**
		 * {@inheritdoc}
		 */
		public static function onInit() {
		}

		/**
		 * {@inheritdoc}
		 */
		public static function onError( OZoneBaseException $err ) {
			return false;
		}
	}