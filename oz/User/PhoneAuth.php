<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\User;

	use OZONE\OZ\Authenticator\Authenticator;
	use OZONE\OZ\Authenticator\CaptchaCodeHelper;
	use OZONE\OZ\Core\Assert;
	use OZONE\OZ\Core\ResponseHolder;
	use OZONE\OZ\Core\SessionsData;
	use OZONE\OZ\Exceptions\ForbiddenException;
	use OZONE\OZ\Ofv\OFormValidator;
	use OZONE\OZ\Sender\SMSUtils;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class PhoneAuth
	{
		const STEP_START    = 1;
		const STEP_VALIDATE = 2;

		/** @var \OZONE\OZ\Core\ResponseHolder */
		private $response;
		private $registration_state = null;
		private $tag                = null;

		/**
		 * PhoneAuth constructor.
		 *
		 * @param string    $tag
		 * @param null|bool $registered
		 */
		public function __construct($tag, $registered = null)
		{
			$this->response = new ResponseHolder(get_class($this));

			$this->tag = "phone_auth:{$tag}";

			if ($registered === true) {
				$this->registration_state = 'registered';
			} elseif ($registered === false) {
				$this->registration_state = 'not-registered';
			}
		}

		/**
		 * @param array $request
		 *
		 * @return $this
		 */
		public function authenticate(array $request)
		{
			$current_step = $this->getStoredData('next_step');

			if (isset($request["step"])) {
				$current_step = intval($request["step"]);
			}

			if ($current_step === self::STEP_VALIDATE) {
				$this->stepValidate($request);
			} else {
				$this->stepStart($request);
			}

			return $this;
		}

		public function isAuthenticated()
		{
			return $this->getStoredData('authenticated');
		}

		public function close()
		{
			$this->setStoredData(null, []);

			return $this;
		}

		public function getAuthenticatedPhone()
		{
			return $this->getStoredData('phone');
		}

		public function getAuthenticatedPhoneCC2()
		{
			return $this->getStoredData('cc2');
		}

		/**
		 * @return \OZONE\OZ\Core\ResponseHolder
		 */
		public function getResponse()
		{
			return $this->response;
		}

		/**
		 * @param string $key
		 *
		 * @return mixed
		 */
		private function getStoredData($key = "")
		{
			$key = $this->tag . (empty($key) ? "" : ":{$key}");

			return SessionsData::get($key);
		}

		/**
		 * @param string $key
		 * @param mixed  $value
		 *
		 * @return $this
		 */
		private function setStoredData($key = "", $value)
		{
			$key = $this->tag . (!empty($key) ? ":{$key}" : "");

			SessionsData::set($key, $value);

			return $this;
		}

		/**
		 * @param array $request
		 *
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 */
		private function stepStart(array $request)
		{
			// do this before log out
			$auth_label = $this->getStoredData('auth_label');
			// log user out
			UsersUtils::logUserOut();

			Assert::assertForm($request, ['cc2', 'phone']);

			// check form
			$fv_obj = new OFormValidator($request);

			$fv_obj->checkForm([
				'cc2'   => ['authorized-only'],
				'phone' => [$this->registration_state]
			]);

			$form = $fv_obj->getForm();

			$cc2   = $form['cc2'];
			$phone = $form['phone'];

			$auth_obj     = new Authenticator($this->tag, $phone);
			$started_once = false;

			if ($auth_obj->canUseLabel($auth_label)) {
				$auth_obj->setLabel($auth_label);
				$started_once = $auth_obj->exists();
			}

			// we check if this user has already started the registration process or not
			if ($started_once) {
				$this->sendAuthCodeResp($auth_obj, $phone, 'OZ_AUTH_CODE_NEW_SENT');
			} else {
				$this->sendAuthCodeResp($auth_obj, $phone, 'OZ_AUTH_CODE_SENT');
			}

			// if user is here then everything 'seems' to be OK:
			// then we can remember him and he goes to next step
			$this->setStoredData(null, [
				'next_step'     => self::STEP_VALIDATE,
				'cc2'           => $cc2,
				'phone'         => $phone,
				'authenticated' => false,
				'auth_label'    => $auth_obj->getLabel()
			]);
		}

		/**
		 * @param array $request
		 *
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 */
		private function stepValidate(array $request)
		{
			$saved_form = $this->getStoredData();

			// assert if previous step is successful
			Assert::assertForm($saved_form, [
				'phone',
				'cc2',
				'auth_label'
			], new ForbiddenException('OZ_PHONE_AUTH_NOT_STARTED'));

			Assert::assertForm($request, ['code']);

			$fv_obj = new OFormValidator($request);

			$fv_obj->checkForm(['code' => null]);

			$code = $request['code'];

			$phone = $saved_form['phone'];

			$auth_label = $saved_form['auth_label'];

			$auth_obj = new Authenticator($this->tag, $phone);
			$auth_obj->setLabel($auth_label);

			if ($auth_obj->validateCode($code)) {
				$this->setStoredData('authenticated', true);
				$this->response->setDone($auth_obj->getMessage());
			} else {
				$this->response->setError($auth_obj->getMessage());
			}
		}

		/**
		 * Send the authentication code
		 *
		 * @param \OZONE\OZ\Authenticator\Authenticator $auth_obj
		 * @param string                                $phone
		 * @param string                                $msg
		 */
		private function sendAuthCodeResp(Authenticator $auth_obj, $phone, $msg)
		{
			$helper  = new CaptchaCodeHelper($auth_obj);
			$captcha = $helper->getCaptcha();

			$sms_sender = SMSUtils::getSenderInstance();

			if ($sms_sender) {
				$generated = $auth_obj->getGenerated();
				$code      = $generated["authCode"];
				$message   = SMSUtils::getSMSMessage(SMSUtils::SMS_TYPE_AUTH_CODE, ['code' => $code]);
				$sms_sender->sendToNumber($phone, $message);
			}

			$this->response->setDone($msg)
						   ->setData(['phone' => $phone, 'captcha' => $captcha['captchaSrc']]);
		}
	}