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

	final class ActionEdit implements ActionInterface
	{

		private $tableDesc = null;

		public function __construct(TableDesccriptor $tableDesc)
		{
			$this->tableDesc = $tableDesc;
		}

		public function getActionUriSub()
		{
			return 'edit';
		}

		public function getSafeRequestMethods()
		{
			return ['PUT'];
		}

		public function getActionFuncName()
		{
			return 'actionEdit';
		}

		public function getActionSourceCode()
		{
		}

		public function isActionRuleFor($rule)
		{
		}
	}