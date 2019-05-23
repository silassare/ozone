<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\User\Services;

	use OZONE\OZ\Core\Assert;
	use OZONE\OZ\Core\BaseService;
	use OZONE\OZ\Core\Context;
	use OZONE\OZ\Exceptions\ForbiddenException;
	use OZONE\OZ\Ofv\OFormValidator;
	use OZONE\OZ\Router\RouteInfo;
	use OZONE\OZ\Router\Router;
	use OZONE\OZ\User\UsersManager;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class Password
	 *
	 * @package OZONE\OZ\User\Services
	 */
	final class Password extends BaseService
	{
		/**
		 * Edit password: verified user only
		 *
		 * @param \OZONE\OZ\Core\Context $context
		 *
		 * @throws \Exception
		 */
		public function actionEditOwnPass(Context $context)
		{
			$users_manager = $context->getUsersManager();
			$users_manager->assertUserVerified();

			$form_data = $context->getRequest()
								 ->getFormData();

			Assert::assertForm($form_data, ['cpass', 'pass', 'vpass']);

			$user_obj = $users_manager->getCurrentUserObject();
			$fv_obj   = new OFormValidator($form_data);

			$fv_obj->checkForm([
				'pass'  => null,
				'vpass' => null
			]);

			$current_pass = $fv_obj->getField('cpass');
			$new_pass     = $fv_obj->getField('pass');

			$users_manager->updateUserPass($user_obj, $new_pass, $current_pass);

			$this->getResponseHolder()
				 ->setDone('OZ_PASSWORD_EDIT_SUCCESS')
				 ->setData($user_obj->asArray());
		}

		/**
		 * Edit password: admin only
		 *
		 * @param \OZONE\OZ\Core\Context $context
		 * @param string                 $uid
		 *
		 * @throws \Exception
		 */
		public function actionEditPassAdmin(Context $context, $uid)
		{
			$users_manager = $context->getUsersManager();
			$users_manager->assertIsAdmin();

			$params = $context->getRequest()
							  ->getFormData();

			Assert::assertForm($params, ['pass', 'vpass']);

			$user_obj = UsersManager::getUserObject($uid);

			if (!$user_obj) {
				throw new ForbiddenException();
			}

			$fv_obj = new OFormValidator($params);

			$fv_obj->checkForm([
				'pass'  => null,
				'vpass' => null
			]);

			$new_pass = $fv_obj->getField("pass");

			$users_manager->updateUserPass($user_obj, $new_pass);

			$this->getResponseHolder()
				 ->setDone('OZ_PASSWORD_EDIT_SUCCESS')
				 ->setData($user_obj->asArray());
		}

		/**
		 * @inheritdoc
		 */
		public static function registerRoutes(Router $router)
		{
			$router
				->patch('/users/{uid}/password/edit', function (RouteInfo $r) {
					$context = $r->getContext();
					$s       = new Password($context);
					$s->actionEditPassAdmin($context, $r->getArg('uid'));

					return $s->writeResponse($context);
				}, ['uid' => '\d+'])
				->patch('/users/password/edit', function (RouteInfo $r) {
					$context = $r->getContext();
					$s       = new Password($context);
					$s->actionEditOwnPass($context);

					return $s->writeResponse($context);
				});
		}
	}
