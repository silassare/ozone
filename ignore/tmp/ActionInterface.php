<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\AppManager;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	interface ActionInterface
	{

		public function __construct(TableDesccriptor $tableDesc);

		/**
		 * get the action url sub name: www.domain.com/myservice/:action_name/
		 *
		 * ex: action_name => edit => www.domain.com/myservice/edit/
		 *
		 * @return string
		 */
		public function getActionUriSub();

		/**
		 * get accepted request methods
		 *
		 * @return array
		 */
		public function getSafeRequestMethods();

		/**
		 * get action function name
		 *
		 * @return string
		 */
		public function getActionFuncName();

		/**
		 * get action source code
		 *
		 * @return string
		 */
		public function getActionSourceCode();

		/**
		 *
		 *
		 *
		 */
		public function isActionRuleFor($rule);
	}