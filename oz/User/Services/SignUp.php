<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\User\Services;

use OZONE\OZ\Core\Assert;
use OZONE\OZ\Core\BaseService;
use OZONE\OZ\Core\Context;
use OZONE\OZ\Core\SettingsManager;
use OZONE\OZ\Db\OZUser;
use OZONE\OZ\Db\OZUsersController;
use OZONE\OZ\Exceptions\ForbiddenException;
use OZONE\OZ\Exceptions\InvalidFormException;
use OZONE\OZ\Ofv\OFormValidator;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;
use OZONE\OZ\User\PhoneAuth;

/**
 * Class SignUp
 */
final class SignUp extends BaseService
{
	/**
	 * @var \OZONE\OZ\User\PhoneAuth
	 */
	private $phone_auth;

	/**
	 * @param \OZONE\OZ\Core\Context $context
	 *
	 * @throws \Throwable
	 */
	public function actionSignUp(Context $context)
	{
		$params = $context->getRequest()
						  ->getFormData();

		$this->phone_auth = new PhoneAuth($context, self::class, false);

		if (isset($params['step'])) {
			$step = (int) ($params['step']);

			if ($step === 3) {
				if ($this->phone_auth->isAuthenticated()) {
					$params['cc2']   = $this->phone_auth->getAuthenticatedPhoneCC2();
					$params['phone'] = $this->phone_auth->getAuthenticatedPhone();
					$this->register($context, $params);
				} else {
					throw new ForbiddenException('OZ_PHONE_AUTH_NOT_VALIDATED');
				}
			} else {
				$response = $this->phone_auth->authenticate($params)
											 ->getResponse();
				$this->getResponseHolder()
					 ->setResponse($response);
			}
		} else {
			throw new InvalidFormException();
		}
	}

	/**
	 * End the registration process with all required user data
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 * @param array                  $request
	 *
	 * @throws \Throwable
	 */
	private function register(Context $context, array $request)
	{
		Assert::assertForm($request, ['cc2', 'phone', 'uname', 'pass', 'vpass', 'birth_date', 'gender']);

		$fv_obj = new OFormValidator($request);

		$fv_obj->checkForm([
			'pass'  => null,
			'vpass' => null,
		]);

		$form_data[OZUser::COL_PHONE]      = $request['phone'];
		$form_data[OZUser::COL_EMAIL]      = isset($request['email']) ? $request['email'] : '';
		$form_data[OZUser::COL_PASS]       = $request['pass'];
		$form_data[OZUser::COL_NAME]       = $request['uname'];
		$form_data[OZUser::COL_GENDER]     = $request['gender'];
		$form_data[OZUser::COL_BIRTH_DATE] = $request['birth_date'];
		$form_data[OZUser::COL_ADD_TIME]   = \time();
		$form_data[OZUser::COL_PICID]      = SettingsManager::get('oz.users', 'OZ_DEFAULT_PICID');
		$form_data[OZUser::COL_CC2]        = $request['cc2'];
		$form_data[OZUser::COL_VALID]      = true;

		$controller = new OZUsersController();
		$user_obj   = $controller->addItem($form_data);

		$context->getUsersManager()
				->logUserIn($user_obj);

		$this->getResponseHolder()
			 ->setDone('OZ_SIGNUP_SUCCESS')
			 ->setData($user_obj->asArray());

		$this->phone_auth->close();
	}

	/**
	 * @inheritdoc
	 */
	public static function registerRoutes(Router $router)
	{
		$router->post('/signup', function (RouteInfo $r) {
			$context = $r->getContext();
			$s       = new self($context);
			$s->actionSignUp($context);

			return $s->respond();
		});
	}
}
