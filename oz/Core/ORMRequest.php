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

	use Gobl\DBAL\Table;
	use Gobl\ORM\ORMRequestBase;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	class ORMRequest extends ORMRequestBase
	{
		/**
		 * @var \OZONE\OZ\Core\Context
		 */
		private $context;

		public function __construct(Context $context, $form)
		{
			parent::__construct($form);

			$this->context = $context;
		}

		/**
		 * @return \OZONE\OZ\Core\Context
		 */
		public function getContext()
		{
			return $this->context;
		}

		/**
		 * @param \Gobl\DBAL\Table $table
		 *
		 * @return \OZONE\OZ\Core\ORMRequest
		 */
		public function createScopedInstance(Table $table)
		{
			$request = $this->getParsedRequest($table);

			return new self($this->context, $request);
		}
	}