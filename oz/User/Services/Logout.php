<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\User\Services;

	use OZONE\OZ\Core\OZoneService;
	use OZONE\OZ\User\OZoneUserUtils;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	/**
	 * Class Logout
	 * @package OZONE\OZ\User\Services
	 */
	final class Logout extends OZoneService {

		/**
		 * Logout constructor.
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * {@inheritdoc}
		 */
		public function execute( $request = array() ) {
			OZoneUserUtils::logOut();
			self::$resp->setDone( 'OZ_USER_LOGOUT' );
		}
	}