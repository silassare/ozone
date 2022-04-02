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

use OZONE\OZ\Core\Context;
use OZONE\OZ\Core\Service;
use OZONE\OZ\Db\OZUser;
use OZONE\OZ\Exceptions\ForbiddenException;
use OZONE\OZ\Exceptions\InvalidFormException;
use OZONE\OZ\Forms\Fields;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;
use OZONE\OZ\Users\PhoneAuth;

/**
 * Class AccountRecovery.
 */
final class AccountRecovery extends Service
{
	public const EDIT_PASS = 3;

	/**
	 * @var \OZONE\OZ\Users\PhoneAuth
	 */
	private PhoneAuth $phone_auth;

	/**
	 * @param \OZONE\OZ\Router\RouteInfo $ri
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
	 * @throws \OZONE\OZ\Exceptions\ForbiddenException
	 * @throws \OZONE\OZ\Exceptions\InvalidFormException
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
	 */
	public function actionRecover(RouteInfo $ri): void
	{
		$context          = $ri->getContext();
		$this->phone_auth = new PhoneAuth($context, self::class, true);
		$fd               = $context->getRequest()->getFormData();

		$step = $ri->getFormField('step');

		if (PhoneAuth::STEP_START === $step || PhoneAuth::STEP_VALIDATE === $step) {
			$response = $this->phone_auth->authenticate($fd)
				->getResponse();
			$this->getJSONResponse()
				->merge($response);
		} elseif (self::EDIT_PASS === $step) {
			if (!$this->phone_auth->isAuthenticated()) {
				throw new ForbiddenException('OZ_PHONE_AUTH_NOT_VALIDATED');
			}

			$um   = $context->getUsersManager();
			$user = $um::searchUserWithPhone($this->phone_auth->getAuthenticatedPhone());

			if (!$user || !$user->getValid()) {
				throw new ForbiddenException();
			}

			$this->editPass($context, $user);
		} else {
			throw new InvalidFormException();
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router->post('/account-recovery', function (RouteInfo $r) {
			$context = $r->getContext();
			$s       = new self($context);

			$s->actionRecover($r);

			return $s->respond();
		});
	}

	/**
	 * End password edit process with all required user data.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 * @param \OZONE\OZ\Db\OZUser    $user
	 *
	 * @throws \OZONE\OZ\Exceptions\InvalidFormException
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
	 */
	private function editPass(Context $context, OZUser $user): void
	{
		$request  = $context->getRequest()
			->getFormData();
		$new_pass = Fields::checkPassVPass($request);

		$um = $context->getUsersManager();

		$um->updateUserPass($user, $new_pass)
			->logUserIn($user);

		$this->getJSONResponse()
			->setDone('OZ_PASSWORD_EDIT_SUCCESS')
			->setData($user->toArray());

		$this->phone_auth->finish();
	}
}
