<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\User;

	use OZONE\OZ\Authenticator\Authenticator;
	use OZONE\OZ\Authenticator\CaptchaCodeHelper;
	use OZONE\OZ\Core\Assert;
	use OZONE\OZ\Core\Context;
	use OZONE\OZ\Core\ResponseHolder;
	use OZONE\OZ\Exceptions\ForbiddenException;
	use OZONE\OZ\Lang\Polyglot;
	use OZONE\OZ\Ofv\OFormValidator;
	use OZONE\OZ\Sender\SMSUtils;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class PhoneAuth
	{
		const STEP_START    = 1;
		const STEP_VALIDATE = 2;

		/**
		 * @var \OZONE\OZ\Core\ResponseHolder
		 */
		private $response;

		/**
		 * @var string
		 */
		private $registration_state;

		/**
		 * @var string
		 */
		private $tag;

		/**
		 * @var \OZONE\OZ\Core\Context
		 */
		private $context;

		/**
		 * PhoneAuth constructor.
		 *
		 * @param \OZONE\OZ\Core\Context $context
		 * @param string                 $tag
		 * @param null|bool              $registered
		 */
		public function __construct(Context $context, $tag, $registered = null)
		{
			$tag            = md5($tag);
			$this->context  = $context;
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
		 * @throws \Exception
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

		/**
		 * @return mixed
		 * @throws \Exception
		 */
		public function isAuthenticated()
		{
			return $this->getStoredData('authenticated');
		}

		/**
		 * @return $this
		 * @throws \Exception
		 */
		public function close()
		{
			$this->setStoredData(null, []);

			return $this;
		}

		/**
		 * @return mixed
		 * @throws \Exception
		 */
		public function getAuthenticatedPhone()
		{
			return $this->getStoredData('phone');
		}

		/**
		 * @return mixed
		 * @throws \Exception
		 */
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
		 * @throws \Exception
		 */
		private function getStoredData($key = '')
		{
			$key = $this->tag . (empty($key) ? '' : ':' . $key);

			return $this->context->getSession()
								 ->get($key);
		}

		/**
		 * @param string $key
		 * @param mixed  $value
		 *
		 * @return $this
		 * @throws \Exception
		 */
		private function setStoredData($key, $value)
		{
			$key = $this->tag . (!empty($key) ? ':' . $key : '');

			$this->context->getSession()
						  ->set($key, $value);

			return $this;
		}

		/**
		 * @param array $request
		 *
		 * @throws \Exception
		 */
		private function stepStart(array $request)
		{
			// do this before log out
			$auth_label = $this->getStoredData('auth_label');

			Assert::assertForm($request, ['cc2', 'phone']);

			// checks form
			$fv_obj = new OFormValidator($request);

			$fv_obj->checkForm([
				'cc2'   => ['authorized-only'],
				'phone' => [$this->registration_state]
			]);

			$form = $fv_obj->getForm();

			$cc2   = $form['cc2'];
			$phone = $form['phone'];

			$auth_obj     = new Authenticator($phone);
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
		 * @throws \Exception
		 */
		private function stepValidate(array $request)
		{
			$saved_form = $this->getStoredData();

			// asserts if previous step is successful
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

			$auth_obj = new Authenticator($phone);
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
		 *
		 * @throws \Exception
		 */
		private function sendAuthCodeResp(Authenticator $auth_obj, $phone, $msg)
		{
			$captcha = CaptchaCodeHelper::getCaptcha($this->context, $auth_obj);

			$sms_sender = SMSUtils::getSenderInstance();

			if ($sms_sender) {
				$generated = $auth_obj->getGenerated();
				$code      = $generated["auth_code"];
				$message   = SMSUtils::getSMSMessage(SMSUtils::SMS_TYPE_AUTH_CODE);
				$message   = Polyglot::translate($message, ['code' => $code]);
				$sms_sender->sendToNumber($phone, $message);
			}

			$this->response->setDone($msg)
						   ->setData(['phone' => $phone, 'captcha' => $captcha['captcha_src']]);
		}
	}
