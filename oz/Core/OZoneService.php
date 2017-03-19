<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Core;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	abstract class OZoneService {
		protected static $resp;

		/**
		 * OZoneService constructor.
		 */
		public function __construct() {
			self::$resp = new OZoneResponsesHolder( $this->getServiceName() );
		}

		/**
		 * called to execute the service with the request form
		 *
		 * @param array $request the request form
		 *
		 * @return void
		 */
		abstract public function execute( $request = array() );

		/**
		 * get the service name
		 *
		 * @return string
		 */
		public function getServiceName() {
			return get_class( $this );
		}

		/**
		 * get the service responses holder object
		 *
		 * @return mixed
		 */

		public function getServiceResponse() {
			return self::$resp->getResponse();
		}
	}