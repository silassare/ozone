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
	use OZONE\OZ\Exceptions\ForbiddenException;
	use OZONE\OZ\Exceptions\NotFoundException;
	use OZONE\OZ\Exceptions\UnauthorizedActionException;
	use OZONE\OZ\Ofv\OFormValidator;
	use OZONE\OZ\User\UsersUtils;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class Password
	 *
	 * @package OZONE\OZ\User\Services
	 */
	final class Password extends BaseService
	{
		/**
		 * @param array $request
		 *
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
		 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
		 * @throws \Exception
		 */
		public function execute(array $request = [])
		{
			if (!isset($request["action"])) {
				throw new ForbiddenException();
			}

			$action = $request["action"];

			switch ($action) {
				case 'edit':
					$this->edit($request);
					break;
				default:
					throw new NotFoundException();
			}
		}

		/**
		 * @param array $request
		 *
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \OZONE\OZ\Exceptions\ForbiddenException
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
		 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
		 */
		private function edit(array $request)
		{
			if (isset($request["uid"])) {
				if (!is_numeric($request["uid"])) {
					throw new ForbiddenException();
				}

				Assert::assertIsAdmin();

				$this->editPassAdmin($request);

			} else {
				Assert::assertUserVerified();
				$this->editPassUser($request);
			}
		}

		/**
		 * Edit password: verified user only
		 *
		 * @param array $request
		 *
		 * @throws \Exception
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
		 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
		 */
		private function editPassUser(array $request)
		{
			Assert::assertForm($request, ['cpass', 'pass', 'vpass']);
			$user_obj = UsersUtils::getCurrentUserObject();

			$fv_obj = new OFormValidator($request);

			$fv_obj->checkForm([
				'pass'  => null,
				'vpass' => null
			]);

			$real_pass = $user_obj->getPass();// encrypted
			$cpass     = $fv_obj->getField("cpass");
			$new_pass  = $fv_obj->getField("pass");
			$crypt_obj = new DoCrypt();

			if (!$crypt_obj->passCheck($cpass, $real_pass)) {
				throw new UnauthorizedActionException("OZ_FIELD_PASS_INVALID");
			}

			if ($crypt_obj->passCheck($new_pass, $real_pass)) {
				throw new UnauthorizedActionException("OZ_PASSWORD_SAME_OLD_AND_NEW_PASS");
			}

			$user_obj->setPass($new_pass)
					 ->save();

			$this->getResponseHolder()
				 ->setDone('OZ_PASSWORD_EDIT_SUCCESS')
				 ->setData($user_obj->asArray());
		}

		/**
		 * Edit password: admin only
		 *
		 * @param array $request
		 *
		 * @throws \Exception
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
		 */
		private function editPassAdmin(array $request)
		{
			$uid = $request["uid"];

			Assert::assertForm($request, ['pass', 'vpass']);

			$user_obj = UsersUtils::getUserObject($uid);

			if (!$user_obj) {
				throw new ForbiddenException();
			}

			$fv_obj = new OFormValidator($request);

			$fv_obj->checkForm([
				'pass'  => null,
				'vpass' => null
			]);

			$real_pass = $user_obj->getPass();// encrypted
			$new_pass  = $fv_obj->getField("pass");
			$crypt_obj = new DoCrypt();

			if ($crypt_obj->passCheck($new_pass, $real_pass)) {
				throw new UnauthorizedActionException("OZ_PASSWORD_SAME_OLD_AND_NEW_PASS");
			}

			$user_obj->setPass($new_pass)
					 ->save();

			$this->getResponseHolder()
				 ->setDone('OZ_PASSWORD_EDIT_SUCCESS')
				 ->setData($user_obj->asArray());
		}
	}
