<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Core;

	use OZONE\OZ\Admin\AdminUtils;
	use OZONE\OZ\Exceptions\MethodNotAllowedException;
	use OZONE\OZ\Exceptions\InvalidFormException;
	use OZONE\OZ\Exceptions\UnauthorizedActionException;
	use OZONE\OZ\Exceptions\UnverifiedUserException;
	use OZONE\OZ\Ofv\OFormUtils;
	use OZONE\OZ\User\UsersUtils;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class Assert
	{

		/**
		 * assert if the request method is authorized
		 *
		 * @param array                  $required_methods the required methods
		 * @param \Exception|string|null $msg              the error message
		 * @param mixed                  $data             the error data
		 *
		 * @throws \OZONE\OZ\Exceptions\MethodNotAllowedException
		 * @throws string
		 */
		public static function assertSafeRequestMethod($required_methods, $msg = 'OZ_ERROR_METHOD_NOT_ALLOWED', $data = null)
		{
			$ok = false;

			foreach ($required_methods as $method) {
				$method = strtoupper($method);

				switch ($method) {
					case 'POST' :
						$ok = RequestHandler::isPost();
						break;
					case 'GET' :
						$ok = RequestHandler::isGet();
						break;
					case 'PUT' :
						$ok = RequestHandler::isPut();
						break;
					case 'OPTIONS' :
						$ok = RequestHandler::isOptions();
						break;
					case 'DELETE' :
						$ok = RequestHandler::isDelete();
						break;
				}

				if ($ok === true) break;
			}

			if ($ok === false) {
				if (!self::isException($msg)) {
					$msg = new MethodNotAllowedException($msg, $data);
				}

				throw $msg;
			}
		}

		/**
		 * assert if the current user is verified
		 *
		 * @param \Exception|string|null $msg  the error message
		 * @param mixed                  $data the error data
		 *
		 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
		 * @throws string
		 */
		public static function assertUserVerified($msg = 'OZ_ERROR_YOU_MUST_LOGIN', $data = null)
		{
			if (!UsersUtils::userVerified()) {
				if (!self::isException($msg)) {
					$msg = new UnverifiedUserException($msg, $data);
				}

				throw $msg;
			}
		}

		/**
		 * assert if the current user is a verified admin
		 *
		 * @param \Exception|string|null $msg  the error message
		 * @param mixed                  $data the error data
		 *
		 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
		 * @throws string
		 */
		public static function assertIsAdmin($msg = 'OZ_YOU_ARE_NOT_ADMIN', $data = null)
		{
			if (!UsersUtils::userVerified() OR !AdminUtils::isAdmin(SessionsData::get('ozone_user:id'))) {
				if (!self::isException($msg)) {
					$msg = new UnverifiedUserException($msg, $data);
				}

				throw $msg;
			}
		}

		/**
		 * assert if the result of a given expression is evaluated to true
		 *
		 * @param mixed                  $expression the expression
		 * @param \Exception|string|null $msg        the error message
		 * @param mixed                  $data       the error data
		 *
		 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
		 * @throws string
		 */
		public static function assertAuthorizeAction($expression, $msg = 'OZ_ERROR_NOT_ALLOWED', $data = null)
		{
			if (!$expression) {
				if (!self::isException($msg)) {
					$msg = new UnauthorizedActionException($msg, $data);
				}

				throw $msg;
			}
		}

		/**
		 * assert if a given result is an ozone error
		 *
		 * @param mixed $result the result
		 *
		 * @throws \Exception
		 */
		public static function assertOperationSuccess($result)
		{
			if (self::isException($result)) throw $result;
		}

		/**
		 * assert if a given form contains has all required fields
		 *
		 * @param mixed                  $form            the form to be checked
		 * @param array                  $required_fields the required fields
		 * @param \Exception|string|null $msg             the error message
		 * @param mixed                  $data            the error data
		 *
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 * @throws string
		 */
		public static function assertForm($form = null, array $required_fields, $msg = 'OZ_ERROR_INVALID_FORM', $data = null)
		{
			if (empty($form) OR !is_array($form)) {
				$safe = false;
			} else {
				$safe = true;
				foreach ($required_fields as $field) {
					if (!isset($form[$field])) {
						$safe = false;
						break;
					}
				}
			}

			if (!$safe) {
				if (!self::isException($msg)) {
					$msg = new InvalidFormException($msg, $data);
				}

				throw $msg;
			}
		}

		/**
		 * Checks for exception
		 *
		 * @param mixed $e
		 *
		 * @return bool
		 */
		private static function isException($e)
		{
			return ($e instanceof \Exception);
		}
	}