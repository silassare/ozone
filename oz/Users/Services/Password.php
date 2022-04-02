<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace OZONE\OZ\Users\Services;

use Exception;
use OZONE\OZ\Core\Assert;
use OZONE\OZ\Core\Context;
use OZONE\OZ\Core\Service;
use OZONE\OZ\Exceptions\ForbiddenException;
use OZONE\OZ\Ofv\OFormValidator;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;
use OZONE\OZ\Users\UsersManager;

/**
 * Class Password.
 */
final class Password extends Service
{
	/**
	 * Edit password: verified user only.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 *
	 * @throws Exception
	 */
	public function actionEditOwnPass(Context $context): void
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
			'vpass' => null,
		]);

		$current_pass = $fv_obj->getField('cpass');
		$new_pass     = $fv_obj->getField('pass');

		$users_manager->updateUserPass($user_obj, $new_pass, $current_pass);

		$this->getJSONResponse()
			->setDone('OZ_PASSWORD_EDIT_SUCCESS')
			->setData($user_obj->toArray());
	}

	/**
	 * Edit password: admin only.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 * @param string                 $uid
	 *
	 * @throws Exception
	 */
	public function actionEditPassAdmin(Context $context, string $uid): void
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
			'vpass' => null,
		]);

		$new_pass = $fv_obj->getField('pass');

		$users_manager->updateUserPass($user_obj, $new_pass);

		$this->getJSONResponse()
			->setDone('OZ_PASSWORD_EDIT_SUCCESS')
			->setData($user_obj->toArray());
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router
			->map(['PATCH', 'POST'], '/users/{uid}/password/edit', function (RouteInfo $r) {
				$context = $r->getContext();
				$s       = new self($context);
				$s->actionEditPassAdmin($context, $r->getParam('uid'));

				return $s->respond();
			}, ['uid' => '\d+']);
		$router->map(['PATCH', 'POST'], '/users/password/edit', function (RouteInfo $r) {
			$context = $r->getContext();
			$s       = new self($context);
			$s->actionEditOwnPass($context);

			return $s->respond();
		});
	}
}
