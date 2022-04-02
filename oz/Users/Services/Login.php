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
use OZONE\OZ\Exceptions\InvalidFormException;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;

/**
 * Class Login.
 */
final class Login extends Service
{
	/**
	 * @param \OZONE\OZ\Core\Context $context
	 *
	 * @throws \OZONE\OZ\Exceptions\InvalidFormException
	 */
	public function actionLogin(Context $context): void
	{
		$users_manager = $context->getUsersManager();
		// And yes! user sent us a form
		// so we check that the form is valid.
		$users_manager->logUserOut();

		$form = $context->getRequest()
			->getFormData();

		if (isset($form['phone'])) {
			$result = $users_manager->tryLogOnWithPhone($context, $form);
		} elseif (isset($form['email'])) {
			$result = $users_manager->tryLogOnWithEmail($context, $form);
		} else {
			throw new InvalidFormException();
		}

		if ($result instanceof OZUser) {
			$this->getJSONResponse()
				->setDone('OZ_USER_ONLINE')
				->setData($result->toArray());
		} else {
			$this->getJSONResponse()
				->setError($result);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router->post('/login', function (RouteInfo $r) {
			$context = $r->getContext();
			$s       = new self($context);
			$s->actionLogin($context);

			return $s->respond();
		});
	}
}
