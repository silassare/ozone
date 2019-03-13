<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\User\Services;

	use OZONE\OZ\Core\Assert;
	use OZONE\OZ\Core\BaseService;
	use OZONE\OZ\Crypt\DoCrypt;
	use OZONE\OZ\Db\OZUsersQuery;
	use OZONE\OZ\Exceptions\ForbiddenException;
	use OZONE\OZ\Exceptions\InvalidFormException;
	use OZONE\OZ\Exceptions\UnauthorizedActionException;
	use OZONE\OZ\Ofv\OFormValidator;
	use OZONE\OZ\User\PhoneAuth;
	use OZONE\OZ\User\UsersUtils;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class AccountRecovery
	 *
	 * @package OZONE\OZ\User\Services
	 */
	final class AccountRecovery extends BaseService
	{
		/**
		 * @var \OZONE\OZ\User\PhoneAuth
		 */
		private $phone_auth;

		/**
		 * @param array $request
		 *
		 * @throws \OZONE\OZ\Exceptions\ForbiddenException
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
		 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
		 * @throws \Exception
		 */
		public function execute(array $request = [])
		{
			$this->phone_auth = new PhoneAuth('svc_account_recover', true);

			if (isset($request["step"])) {
				$step = intval($request["step"]);

				if ($step === 3) {
					if ($this->phone_auth->isAuthenticated()) {
						$request['cc2']   = $this->phone_auth->getAuthenticatedPhoneCC2();
						$request['phone'] = $this->phone_auth->getAuthenticatedPhone();
						$this->editPass($request);
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
		 * @param array $request
		 *
		 * @throws \Exception
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
		 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
		 */
		private function editPass(array $request)
		{
			Assert::assertForm($request, ['phone', 'cc2', 'pass', 'vpass']);

			$fv_obj = new OFormValidator($request);

			$fv_obj->checkForm([
				'pass'  => null,
				'vpass' => null
			]);

			$q = new OZUsersQuery();

			$q->filterByPhone($fv_obj->getField("phone"))
			  ->filterByValid(true);

			$user_obj = $q->find()->fetchClass();

			if (!$user_obj) {
				throw new ForbiddenException();
			}

			$old_pass  = $user_obj->getPass();// encrypted
			$new_pass  = $fv_obj->getField("pass");
			$crypt_obj = new DoCrypt();

			if ($crypt_obj->passCheck($new_pass, $old_pass)) {
				throw new UnauthorizedActionException("OZ_PASSWORD_SAME_OLD_AND_NEW_PASS");
			}

			$user_obj->setPass($new_pass)
					 ->save();

			UsersUtils::logUserIn($user_obj);

			$this->getResponseHolder()
				 ->setDone('OZ_PASSWORD_EDIT_SUCCESS')
				 ->setData($user_obj->asArray());

			$this->phone_auth->close();
		}
	}
