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

	use Gobl\CRUD\CRUDColumnUpdate;
	use Gobl\CRUD\CRUDCreate;
	use Gobl\CRUD\CRUDDelete;
	use Gobl\CRUD\CRUDDeleteAll;
	use Gobl\CRUD\CRUDRead;
	use Gobl\CRUD\CRUDReadAll;
	use Gobl\CRUD\CRUDUpdate;
	use Gobl\CRUD\CRUDUpdateAll;
	use Gobl\CRUD\Handler\Interfaces\CRUDHandlerInterface;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	class CRUDHandler implements CRUDHandlerInterface
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
			$this->context = $context;
		}

		/**
		 * Gets the context.
		 *
		 * @return \OZONE\OZ\Core\Context
		 */
		protected function getContext()
		{
			return $this->context;
		}

		/**
		 * @throws \OZONE\OZ\Exceptions\BaseException
		 */
		protected  function assertIsAdmin(){
			$this->getContext()->getUsersManager()->assertIsAdmin();
		}
		/**
		 * @throws \OZONE\OZ\Exceptions\BaseException
		 */
		protected  function assertUserVerified(){
			$this->getContext()->getUsersManager()->assertUserVerified();
		}

		/**
		 * @param \Gobl\CRUD\CRUDCreate $action
		 *
		 * @return bool
		 */
		public function onBeforeCreate(CRUDCreate $action)
		{
			return false;
		}

		/**
		 * @param \Gobl\CRUD\CRUDRead $action
		 *
		 * @return boolean
		 */
		public function onBeforeRead(CRUDRead $action)
		{
			return false;
		}

		/**
		 * @param \Gobl\CRUD\CRUDUpdate $action
		 *
		 * @return boolean
		 */
		public function onBeforeUpdate(CRUDUpdate $action)
		{
			return false;
		}

		/**
		 * @param \Gobl\CRUD\CRUDDelete $action
		 *
		 * @return boolean
		 */
		public function onBeforeDelete(CRUDDelete $action)
		{
			return false;
		}

		/**
		 * @param \Gobl\CRUD\CRUDReadAll $action
		 *
		 * @return boolean
		 */
		public function onBeforeReadAll(CRUDReadAll $action)
		{
			return false;
		}

		/**
		 * @param \Gobl\CRUD\CRUDUpdateAll $action
		 *
		 * @return boolean
		 */
		public function onBeforeUpdateAll(CRUDUpdateAll $action)
		{
			return false;
		}

		/**
		 * @param \Gobl\CRUD\CRUDDeleteAll $action
		 *
		 * @return boolean
		 */
		public function onBeforeDeleteAll(CRUDDeleteAll $action)
		{
			return false;
		}

		/**
		 * @param \Gobl\CRUD\CRUDColumnUpdate $action
		 *
		 * @return boolean
		 */
		public function onBeforeColumnUpdate(CRUDColumnUpdate $action)
		{
			return false;
		}

		/**
		 * @param mixed $entity
		 */
		public function onAfterCreateEntity($entity) { }

		/**
		 * @param mixed $entity
		 */
		public function onAfterReadEntity($entity) { }

		/**
		 * @param mixed $entity
		 */
		public function onBeforeUpdateEntity($entity) { }

		/**
		 * @param mixed $entity
		 */
		public function onAfterUpdateEntity($entity) { }

		/**
		 * @param mixed $entity
		 */
		public function onBeforeDeleteEntity($entity) { }

		/**
		 * @param mixed $entity
		 */
		public function onAfterDeleteEntity($entity) { }

		/**
		 * @return boolean
		 */
		public function shouldWritePkColumn()
		{
			return false;
		}

		/**
		 * @return boolean
		 */
		public function shouldWritePrivateColumn()
		{
			return false;
		}

		/**
		 * @param array $form
		 */
		public function autoFillCreateForm(array &$form) { }
	}