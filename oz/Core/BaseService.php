<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Core;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	abstract class BaseService
	{
		/** @var \OZONE\OZ\Core\ResponseHolder */
		private $response_holder;

		/**
		 * BaseService constructor.
		 */
		public function __construct()
		{
			$this->response_holder = new ResponseHolder($this->getServiceName());
		}

		/**
		 * Called to execute the service with the request form.
		 *
		 * @param array $request the request form
		 *
		 * @return void
		 */
		abstract public function execute(array $request = []);

		/**
		 * Gets the service name.
		 *
		 * @return string
		 */
		public function getServiceName()
		{
			return get_class($this);
		}

		/**
		 * Gets the service responses holder object.
		 *
		 * @return \OZONE\OZ\Core\ResponseHolder
		 */
		public function getResponseHolder()
		{
			return $this->response_holder;
		}
	}