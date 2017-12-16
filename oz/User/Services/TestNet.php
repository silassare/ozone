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

	use OZONE\OZ\Core\BaseService;
	use OZONE\OZ\Core\SessionsData;
	use OZONE\OZ\User\UsersUtils;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class TestNet
	 *
	 * @package OZONE\OZ\User\Services
	 */
	class TestNet extends BaseService
	{
		/**
		 * {@inheritdoc}
		 *
		 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
		 */
		public function execute(array $request = [])
		{
			$data = [];
			if (UsersUtils::userVerified()) {
				$user_obj = UsersUtils::getCurrentUserObject();
				// user is already logged
				// UsersUtils::logUserIn($user_obj);
				$data['ok']            = 1;
				$data['_current_user'] = $user_obj->asArray();
			} else {
				$data['ok'] = 0;
				$step       = SessionsData::get('svc_sign_up:step');
				$phone      = SessionsData::get('svc_sign_up:phone');

				if (!empty($step) AND !empty($phone) AND $step === Signup::SIGNUP_STEP_VALIDATE) {
					$data['_info_sign_up'] = ['step' => $step, 'phone' => $phone];
				}
			}

			$this->getResponseHolder()
				 ->setData($data);
		}
	}