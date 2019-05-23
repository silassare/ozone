<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Core;

	use Gobl\CRUD\Handler\CRUDHandlerStrict;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	class CRUDHandlerBase extends CRUDHandlerStrict
	{
		/**
		 * @var \OZONE\OZ\Core\Context
		 */
		private $context;

		/**
		 * CRUDHandlerBase constructor.
		 *
		 * @param \OZONE\OZ\Core\Context $context
		 */
		public function __construct(Context $context)
		{
			parent::__construct();

			$this->context = $context;
		}

		/**
		 * Gets the context.
		 *
		 * @return \OZONE\OZ\Core\Context
		 */
		public function getContext()
		{
			return $this->context;
		}
	}