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

	use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
	use Gobl\ORM\Exceptions\ORMControllerFormException;
	use OZONE\OZ\Core\Assert;
	use OZONE\OZ\Core\BaseService;
	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\Db\OZUser;
	use OZONE\OZ\Db\OZUsersController;
	use OZONE\OZ\Exceptions\ForbiddenException;
	use OZONE\OZ\Exceptions\InvalidFieldException;
	use OZONE\OZ\Exceptions\InvalidFormException;
	use OZONE\OZ\Ofv\OFormValidator;
	use OZONE\OZ\User\PhoneAuth;
	use OZONE\OZ\User\UsersUtils;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class SignUp
	 *
	 * @package OZONE\OZ\User\Services
	 */
	final class SignUp extends BaseService
	{
		/**
		 * @var \OZONE\OZ\User\PhoneAuth
		 */
		private $phone_auth;

		/**
		 * @param array $request
		 *
		 * @throws \OZONE\OZ\Exceptions\ForbiddenException
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
		 */
		public function executeSub(array $request = [])
		{
			$this->phone_auth = new PhoneAuth('svc_sign_up', false);

			if (isset($request["step"])) {
				$step = intval($request["step"]);

				if ($step === 3) {
					if ($this->phone_auth->isAuthenticated()) {
						$request['cc2']   = $this->phone_auth->getAuthenticatedPhoneCC2();
						$request['phone'] = $this->phone_auth->getAuthenticatedPhone();
						$this->register($request);
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
		 * Executes the service.
		 *
		 * @param array $request the request parameters
		 *
		 * @throws \OZONE\OZ\Exceptions\ForbiddenException
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\InvalidFieldException
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
		 */
		public function execute(array $request = [])
		{
			$success = true;
			$error   = null;

			try {
				$this->executeSub($request);
			} catch (ORMControllerFormException $e) {
				$success = false;
				$error   = $e;
			} catch (TypesInvalidValueException $e) {
				$success = false;
				$error   = $e;
			}

			if (!$success) {
				$this->tryConvertException($error);
			}
		}

		/**
		 * Converts Gobl exceptions unto OZone exceptions.
		 *
		 * @param \Exception $error the exception
		 *
		 * @throws \Exception
		 * @throws \OZONE\OZ\Exceptions\InvalidFieldException
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 */
		public static function tryConvertException(\Exception $error)
		{
			if ($error instanceof ORMControllerFormException) {
				throw new InvalidFormException(null, [$error->getMessage(), $error->getData()], $error);
			}

			if ($error instanceof TypesInvalidValueException) {
				$data = $error->getData();
				throw new InvalidFieldException($error->getMessage(), $data, $error);
			}

			throw $error;
		}

		/**
		 * End the registration process with all required user data
		 *
		 * @param array $request
		 *
		 * @throws \Exception
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
		 */
		private function register(array $request)
		{
			Assert::assertForm($request, ['cc2', 'phone', 'uname', 'pass', 'vpass', 'birth_date', 'gender']);

			$fv_obj = new OFormValidator($request);

			$fv_obj->checkForm([
				'pass'  => null,
				'vpass' => null,
			]);

			$form_data[OZUser::COL_PHONE]        = $request['phone'];
			$form_data[OZUser::COL_EMAIL]        = isset($request['email']) ? $request['email'] : "";
			$form_data[OZUser::COL_PASS]         = $request['pass'];
			$form_data[OZUser::COL_NAME]         = $request['uname'];
			$form_data[OZUser::COL_GENDER]       = $request['gender'];
			$form_data[OZUser::COL_BIRTH_DATE]   = $request['birth_date'];
			$form_data[OZUser::COL_SIGN_UP_TIME] = time();
			$form_data[OZUser::COL_PICID]        = SettingsManager::get('oz.users', 'OZ_DEFAULT_PICID');
			$form_data[OZUser::COL_CC2]          = $request['cc2'];
			$form_data[OZUser::COL_VALID]        = true;

			$controller = new OZUsersController();
			$user_obj   = $controller->addItem($form_data);

			UsersUtils::logUserIn($user_obj);

			$this->getResponseHolder()
				 ->setDone('OZ_SIGNUP_SUCCESS')
				 ->setData($user_obj->asArray());

			$this->phone_auth->close();
		}
	}
