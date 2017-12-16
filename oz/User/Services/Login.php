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
	use OZONE\OZ\Ofv\OFormValidator;
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
		 */
		public function execute(array $request = [])
		{
			// And yes! user sent us a form
			// so we check that the form is valid.
			UsersUtils::logUserOut();

			$user_obj = null;

			if (isset($request['phone'])) {
				$user_obj = $this->withPhone($request);
			} elseif (isset($request['email'])) {
				$user_obj = $this->withEmail($request);
			} else {
				throw new ForbiddenException('OZ_ERROR_INVALID_FORM');
			}

			if ($user_obj instanceof OZUser) {
				$this->getResponseHolder()->setDone('OZ_USER_ONLINE')
						   ->setData($user_obj->asArray());
			} else {
				$err_msg = $user_obj;
				$this->getResponseHolder()->setError($err_msg);
			}
		}

		/**
		 * @param array $request
		 *
		 * @return \OZONE\OZ\Db\OZUser|string
		 */
		private function withPhone(array $request)
		{
			Assert::assertForm($request, ['phone', 'pass']);

			$fv_obj = new OFormValidator($request);

			$fv_obj->checkForm(['phone' => ['registered'], 'pass' => null]);

			$form  = $fv_obj->getForm();
			$phone = $form['phone'];
			$pass  = $form['pass'];

			return UsersUtils::tryLogOnWithPhone($phone, $pass);
		}

		/**
		 * @param array $request
		 *
		 * @return \OZONE\OZ\Db\OZUser|string
		 */
		private function withEmail(array $request)
		{
			Assert::assertForm($request, ['email', 'pass']);

			$fv_obj = new OFormValidator($request);

			$fv_obj->checkForm(['email' => ['registered'], 'pass' => null]);

			$form  = $fv_obj->getForm();
			$email = $form['email'];
			$pass  = $form['pass'];

			return UsersUtils::tryLogOnWithEmail($email, $pass);
		}
	}