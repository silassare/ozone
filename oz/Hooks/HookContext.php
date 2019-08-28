<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Hooks;

	use OZONE\OZ\Core\Context;
	use OZONE\OZ\Http\Request;
	use OZONE\OZ\Http\Response;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class HookContext
	{
		/**
		 * @var \OZONE\OZ\Http\Request
		 */
		private $request;

		/**
		 * @var \OZONE\OZ\Http\Response
		 */
		private $response;

		/**
		 * @var \OZONE\OZ\Core\Context
		 */
		private $context;

		public function __construct(Context $context)
		{
			$this->request  = $context->getRequest();
			$this->response = $context->getResponse();
			$this->context  = $context;
		}

		/**
		 * @return \OZONE\OZ\Core\Context
		 */
		public function getContext()
		{
			return $this->context;
		}

		/**
		 * @return \OZONE\OZ\Http\Request
		 */
		public function getRequest()
		{
			return $this->request;
		}

		/**
		 * @param \OZONE\OZ\Http\Request $request
		 *
		 * @return $this
		 */
		public function setRequest(Request $request)
		{
			$this->request = $request;

			return $this;
		}

		/**
		 * @return \OZONE\OZ\Http\Response
		 */
		public function getResponse()
		{
			return $this->response;
		}

		/**
		 * @param \OZONE\OZ\Http\Response $response
		 *
		 * @return \OZONE\OZ\Hooks\HookContext
		 */
		public function setResponse(Response $response)
		{
			$this->response = $response;

			return $this;
		}
	}