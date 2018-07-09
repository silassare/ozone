<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\User\Services;

	use OZONE\OZ\Core\Assert;
	use OZONE\OZ\Core\BaseService;
	use OZONE\OZ\Db\Base\OZUser;
	use OZONE\OZ\User\UsersUtils;
	use OZONE\OZ\Exceptions\ForbiddenException;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class Login
	 *
	 * @package OZONE\OZ\User\Services
	 */
	final class Login extends BaseService
	{
		/**
		 * {@inheritdoc}
		 * @throws \Exception
		 */
		public function execute(array $request = [])
		{
			// And yes! user sent us a form
			// so we check that the form is valid.
			UsersUtils::logUserOut();

			$result = null;

			if (isset($request['phone'])) {
				Assert::assertForm($request, ['phone', 'pass']);
				$result = UsersUtils::tryLogOnWithPhone($request["phone"], $request["pass"]);
			} elseif (isset($request['email'])) {
				Assert::assertForm($request, ['email', 'pass']);
				$result = UsersUtils::tryLogOnWithEmail($request["email"], $request["pass"]);
			} else {
				throw new ForbiddenException('OZ_ERROR_INVALID_FORM');
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
	}