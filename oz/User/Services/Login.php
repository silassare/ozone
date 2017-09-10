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

	use OZONE\OZ\Core\OZoneAssert;
	use OZONE\OZ\Core\OZoneService;
	use OZONE\OZ\Ofv\OFormValidator;
	use OZONE\OZ\User\OZoneUserUtils;
	use OZONE\OZ\Exceptions\OZoneForbiddenException;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class Login
	 *
	 * @package OZONE\OZ\User\Services
	 */
	final class Login extends OZoneService
	{

		/**
		 * Login constructor.
		 */
		public function __construct()
		{
			parent::__construct();
		}

		/**
		 * {@inheritdoc}
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneInvalidFormException
		 * @throws \OZONE\OZ\Exceptions\OZoneUnverifiedUserException
		 */
		public function execute($request = [])
		{
			// et oui! user nous a soumit un formulaire alors on verifie que le formulaire est valide.
			OZoneUserUtils::logOut();

			$result = null;

			if (isset($request['phone'])) {
				$result = $this->withPhone($request);
			} elseif (isset($request['email'])) {
				$result = $this->withEmail($request);
			} else {
				throw new OZoneForbiddenException('OZ_ERROR_INVALID_FORM');
			}

			if (is_array($result)) {
				// on connecte user
				// et on lui renvois les infos sur lui
				self::$resp->setDone('OZ_USER_ONLINE')
						   ->setData($result);
			} else {
				$err_msg = $result;
				self::$resp->setError($err_msg);
			}
		}

		private function withPhone(array $request)
		{
			OZoneAssert::assertForm($request, ['phone', 'pass']);

			$fv_obj = new OFormValidator($request);

			$fv_obj->checkForm(['phone' => ['registered'], 'pass' => null]);

			$form  = $fv_obj->getForm();
			$phone = $form['phone'];
			$pass  = $form['pass'];

			return OZoneUserUtils::tryLogOnWith('phone', $phone, $pass);
		}

		private function withEmail(array $request)
		{
			OZoneAssert::assertForm($request, ['email', 'pass']);

			$fv_obj = new OFormValidator($request);

			$fv_obj->checkForm(['email' => ['registered'], 'pass' => null]);

			$form  = $fv_obj->getForm();
			$email = $form['email'];
			$pass  = $form['pass'];

			return OZoneUserUtils::tryLogOnWith('email', $email, $pass);
		}
	}