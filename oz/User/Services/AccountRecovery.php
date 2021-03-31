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
use OZONE\OZ\Exceptions\ForbiddenException;
use OZONE\OZ\Exceptions\InvalidFormException;
use OZONE\OZ\Ofv\OFormValidator;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;
use OZONE\OZ\User\PhoneAuth;

/**
 * Class AccountRecovery
 */
final class AccountRecovery extends BaseService
{
	/**
	 * @var \OZONE\OZ\User\PhoneAuth
	 */
	private $phone_auth;

	/**
	 * @param \OZONE\OZ\Core\Context $context
	 *
	 * @throws \OZONE\OZ\Exceptions\BaseException
	 * @throws \Exception
	 */
	public function actionRecover(Context $context)
	{
		$this->phone_auth = new PhoneAuth($context, self::class, true);
		$request          = $context->getRequest()
									->getFormData();

		if (isset($request['step'])) {
			$step = (int) ($request['step']);

			if ($step === 3) {
				if ($this->phone_auth->isAuthenticated()) {
					$request['cc2']   = $this->phone_auth->getAuthenticatedPhoneCC2();
					$request['phone'] = $this->phone_auth->getAuthenticatedPhone();
					$this->editPass($context, $request);
				} else {
					throw new ForbiddenException('OZ_PHONE_AUTH_NOT_VALIDATED');
				}
			} else {
				$response = $this->phone_auth->authenticate($request)
											 ->getResponse();
				$this->getResponseHolder()
					 ->setResponse($response);
			}
		} else {
			throw new InvalidFormException();
		}
	}

	/**
	 * End password edit process with all required user data
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 * @param array                  $request
	 *
	 * @throws \Exception
	 */
	private function editPass(Context $context, array $request)
	{
		Assert::assertForm($request, ['phone', 'cc2', 'pass', 'vpass']);

		$fv_obj = new OFormValidator($request);

		$fv_obj->checkForm([
			'pass'  => null,
			'vpass' => null,
		]);

		$um       = $context->getUsersManager();
		$user_obj = $um::searchUserWithPhone($fv_obj->getField('phone'));

		if (!$user_obj || !$user_obj->getValid()) {
			throw new ForbiddenException();
		}

		$new_pass = $fv_obj->getField('pass');

		$um->updateUserPass($user_obj, $new_pass)
		   ->logUserIn($user_obj);

		$this->getResponseHolder()
			 ->setDone('OZ_PASSWORD_EDIT_SUCCESS')
			 ->setData($user_obj->asArray());

		$this->phone_auth->close();
	}

	/**
	 * @inheritdoc
	 */
	public static function registerRoutes(Router $router)
	{
		$router->post('/account-recovery', function (RouteInfo $r) {
			$context = $r->getContext();
			$s       = new self($context);
			$s->actionRecover($context);

			return $s->respond();
		});
	}
}
