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
	use OZONE\OZ\Db\OZUser;
	use OZONE\OZ\Exceptions\InvalidFormException;
	use OZONE\OZ\Router\RouteInfo;
	use OZONE\OZ\Router\Router;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class Login
	 *
	 * @package OZONE\OZ\User\Services
	 */
	final class Login extends BaseService
	{
		/**
		 * @param \OZONE\OZ\Core\Context $context
		 *
		 * @throws \Exception
		 */
		public function actionLogin(Context $context)
		{
			$users_manager = $context->getUsersManager();
			// And yes! user sent us a form
			// so we check that the form is valid.
			$users_manager->logUserOut();

			$result = null;
			$params = $context->getRequest()
							  ->getFormData();

			if (isset($params['phone'])) {
				Assert::assertForm($params, ['phone', 'pass']);
				$result = $users_manager->tryLogOnWithPhone($params["phone"], $params["pass"]);
			} elseif (isset($params['email'])) {
				Assert::assertForm($params, ['email', 'pass']);
				$result = $users_manager->tryLogOnWithEmail($params["email"], $params["pass"]);
			} else {
				throw new InvalidFormException();
			}

			if ($result instanceof OZUser) {
				$this->getResponseHolder()
					 ->setDone('OZ_USER_ONLINE')
					 ->setData($result->asArray());
			} else {
				$this->getResponseHolder()
					 ->setError($result);
			}
		}

		/**
		 * @inheritdoc
		 */
		public static function registerRoutes(Router $router)
		{
			$router->get('/login', function (RouteInfo $r) {
				$context = $r->getContext();
				$s       = new Login($context);
				$s->actionLogin($context);

				return $s->writeResponse($context);
			});
		}
	}