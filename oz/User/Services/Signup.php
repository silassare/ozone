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

	use OZONE\OZ\Authenticator\Authenticator;
	use OZONE\OZ\Authenticator\CaptchaCodeHelper;
	use OZONE\OZ\Core\Assert;
	use OZONE\OZ\Core\BaseService;
	use OZONE\OZ\Core\SessionsData;
	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\Db\OZUser;
	use OZONE\OZ\Exceptions\ForbiddenException;
	use OZONE\OZ\Ofv\OFormValidator;
	use OZONE\OZ\User\UsersUtils;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class Signup
	 *
	 * @package OZONE\OZ\User\Services
	 */
	final class Signup extends BaseService
	{
		const SIGNUP_STEP_START    = 1;
		const SIGNUP_STEP_VALIDATE = 2;
		const SIGNUP_STEP_END      = 3;

		/**
		 * BaseServiceSignup constructor.
		 */
		public function __construct()
		{
			parent::__construct();
		}

		public function execute(array $request = [])
		{
			$step = SessionsData::get('svc_sign_up:next_step');

			if (isset($request['step'])) {
				$step = intval($request['step']);
			}

			switch ($step) {
				case self::SIGNUP_STEP_START :
					$this->stepStart($request);
					break;
				case self::SIGNUP_STEP_VALIDATE :
					$this->stepValidate($request);
					break;
				case self::SIGNUP_STEP_END :
					$this->stepEnd($request);
					break;
				default:
					$this->getResponseHolder()->setError('OZ_ERROR_INVALID_FORM')
							   ->setKey('step', $step);
			}
		}

		/**
		 * start a new registration process
		 *
		 * @param array $request
		 *
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 */
		private function stepStart(array $request)
		{
			// do this before log out
			$authLabel = SessionsData::get('svc_sign_up:auth:authLabel');
			// log user out
			UsersUtils::logUserOut();

			Assert::assertForm($request, ['cc2', 'phone']);

			// check form
			$fv_obj = new OFormValidator($request);

			$fv_obj->checkForm(['cc2' => ['authorized-only'], 'phone' => ['not-registered']]);

			$form = $fv_obj->getForm();

			$cc2   = $form['cc2'];
			$phone = $form['phone'];

			$auth_obj = new Authenticator('svc_sign_up', $phone);
			$knowOnce = false;

			if ($auth_obj->canUseLabel($authLabel)) {
				$auth_obj->setLabel($authLabel);
				$knowOnce = $auth_obj->exists();
			}

			// we check if this user has already started the registration process or not
			if (!$knowOnce) {
				$this->sendAuthCodeResp($auth_obj, $phone, 'OZ_AUTH_CODE_SENT');
			} else {
				$this->sendAuthCodeResp($auth_obj, $phone, 'OZ_AUTH_CODE_NEW_SENT');
			}

			// if user is here then everything 'seems' to be OK:
			// then we can remember him and he goes to next step
			SessionsData::set('svc_sign_up', [
				'next_step'    => self::SIGNUP_STEP_VALIDATE,
				'cc2'          => $cc2,
				'phone'        => $phone,
				'num_verified' => false
			]);
		}

		/**
		 * validate the user login (phone or email)
		 *
		 * @param array $request
		 *
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 */
		private function stepValidate(array $request)
		{
			$saved_form = SessionsData::get('svc_sign_up');
			$authLabel  = SessionsData::get('svc_sign_up:auth:authLabel');

			// assert if previous step is successful
			Assert::assertForm($saved_form AND $authLabel, [
				'phone',
				'cc2'
			], new ForbiddenException('OZ_SIGNUP_STEP_1_INVALID'));

			Assert::assertForm($request, ['code']);

			$fv_obj = new OFormValidator($request);

			$fv_obj->checkForm(['code' => null]);

			$code = $request['code'];

			$phone = $saved_form['phone'];

			$auth_obj = new Authenticator('svc_sign_up', $phone);
			$auth_obj->setLabel($authLabel);

			if ($auth_obj->validateCode($code)) {
				SessionsData::set('svc_sign_up:next_step', self::SIGNUP_STEP_END);
				SessionsData::set('svc_sign_up:num_verified', true);

				$this->getResponseHolder()->setDone($auth_obj->getMessage());
			} else {
				$this->getResponseHolder()->setError($auth_obj->getMessage());
			}
		}

		/**
		 * end the registration process with all required user data
		 *
		 * @param array $request
		 *
		 * @throws \Exception
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
		 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
		 */
		private function stepEnd(array $request)
		{
			$saved_form = SessionsData::get('svc_sign_up');

			// assert if previous step is successful
			Assert::assertForm($saved_form, [
				'cc2',
				'phone',
				'num_verified'
			], new ForbiddenException('OZ_SESSION_INVALID'));

			if (!$saved_form['num_verified']) {
				SessionsData::remove('svc_sign_up');
				Assert::assertAuthorizeAction(false, 'OZ_SIGNUP_STEP_2_INVALID');
			}

			$cc2   = $saved_form['cc2'];
			$phone = $saved_form['phone'];

			Assert::assertForm($request, ['uname', 'pass', 'vpass', 'birth_date', 'gender']);

			$fv_obj = new OFormValidator($request);

			$fv_obj->checkForm([
				'uname'     => null,
				'pass'      => null,
				'vpass'     => null,
				'birth_date' => null,
				'gender'    => null
			]);

			$form = $fv_obj->getForm();

			$uname     = $form['uname'];
			$pass      = $form['pass'];
			$birthdate = $form['birth_date'];
			$gender    = $form['gender'];

			$user_obj = new OZUser();

			$user_obj->setName($uname)
					 ->setPhone($phone)
					 ->setEmail('')
					 ->setPass($pass)
					 ->setGender($gender)
					 ->setSignUpTime(time())
					 ->setBirthDate($birthdate)
					 ->setCc2($cc2)
					 ->setValid(1)
					 ->setPicid(SettingsManager::get('oz.user', 'OZ_DEFAULT_PICID'))
					 ->save();

			SessionsData::remove('svc_sign_up');

			UsersUtils::logUserIn($user_obj);

			$this->getResponseHolder()->setDone('OZ_SIGNUP_SUCCESS')
					   ->setData($user_obj->asArray());
		}

		/**
		 * send the authentication code
		 *
		 * @param \OZONE\OZ\Authenticator\Authenticator $auth_obj
		 * @param string                                $phone
		 * @param string                                $msg
		 */
		private function sendAuthCodeResp(Authenticator $auth_obj, $phone, $msg)
		{
			$helper    = new CaptchaCodeHelper($auth_obj);
			$captcha   = $helper->getCaptcha();
			$generated = $auth_obj->getGenerated();

			SessionsData::set('svc_sign_up:auth', $generated);

			$this->getResponseHolder()->setDone($msg)
					   ->setData(['phone' => $phone, 'captcha' => $captcha['captchaSrc']]);
		}
	}
