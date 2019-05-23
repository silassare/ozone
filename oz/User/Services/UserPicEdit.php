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
	use OZONE\OZ\FS\PPicUtils;
	use OZONE\OZ\Router\RouteInfo;
	use OZONE\OZ\Router\Router;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class UserPicEdit
	 *
	 * @package OZONE\OZ\User\Services
	 */
	class UserPicEdit extends BaseService
	{
		/**
		 * @param \OZONE\OZ\Core\Context $context
		 *
		 * @throws \Exception
		 */
		public function actionPicEdit(Context $context)
		{
			$request = $context->getRequest();
			$params  = $request->getFormData();

			$users_manager = $context->getUsersManager();

			$users_manager->assertUserVerified();
			Assert::assertForm($params, ['for_id']);

			$label = 'file';

			if (isset($params['label'])) {
				$label = $params['label'];
			}

			Assert::assertAuthorizeAction(in_array($label, ['file', 'file_id', 'def']));

			$for_id = $params['for_id'];

			$user_obj   = $users_manager->getCurrentUserObject();
			$uid        = $user_obj->getId();
			$file_label = 'OZ_FILE_LABEL_USER_PPIC';
			$msg        = 'OZ_PROFILE_PIC_CHANGED';

			Assert::assertAuthorizeAction($uid === $for_id);

			$ppic_obj = new PPicUtils($uid);

			if ($label === 'file_id') {
				Assert::assertForm($params, ['file_id', 'file_key']);
				$picid = $ppic_obj->fromFileId($params['file_id'], $params['file_key'], $params, $file_label);
			} elseif ($label === 'file') {
				$uploaded_files = $request->getUploadedFiles();
				Assert::assertForm($uploaded_files, ['photo']);
				$picid = $ppic_obj->fromUploadedFile($uploaded_files['photo'], $params, $file_label);
			} else {//def
				$picid = PPicUtils::getDefault();
				$msg   = 'OZ_PROFILE_PIC_SET_TO_DEFAULT';
			}

			$user_obj->setPicid($picid)
					 ->save();

			$this->getResponseHolder()
				 ->setDone($msg)
				 ->setData($user_obj->asArray());
		}

		/**
		 * @inheritdoc
		 */
		public static function registerRoutes(Router $router)
		{
			$router->patch('/users/pic/edit', function (RouteInfo $r) {
				$context = $r->getContext();
				$s       = new UserPicEdit($context);
				$s->actionPicEdit($context);

				return $s->writeResponse($context);
			});
		}
	}