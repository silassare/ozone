<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Core;

	use OZONE\OZ\Admin\AdminUtils;
	use OZONE\OZ\Exceptions\ForbiddenException;
	use OZONE\OZ\Exceptions\MethodNotAllowedException;
	use OZONE\OZ\Exceptions\InvalidFormException;
	use OZONE\OZ\Exceptions\UnauthorizedActionException;
	use OZONE\OZ\Exceptions\UnverifiedUserException;
	use OZONE\OZ\User\UsersUtils;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class Assert
	{

		/**
		 * assert if the request method is authorized
		 *
		 * @param array                  $required_methods the required methods
		 * @param \Exception|string|null $error_msg        the error message
		 * @param mixed                  $error_data       the error data
		 *
		 * @throws \OZONE\OZ\Exceptions\MethodNotAllowedException
		 * @throws string
		 */
		public static function assertSafeRequestMethod($required_methods, $error_msg = 'OZ_ERROR_METHOD_NOT_ALLOWED', $error_data = null)
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
					case 'PATCH' :
						$ok = RequestHandler::isPatch();
						break;
					case 'DELETE' :
						$ok = RequestHandler::isDelete();
						break;
					case 'OPTIONS' :
						$ok = RequestHandler::isOptions();
						break;
				}

				if ($ok === true) break;
			}

			if ($ok === false) {
				if (!self::isException($error_msg)) {
					$error_msg = new MethodNotAllowedException($error_msg, $error_data);
				}

				throw $error_msg;
			}
		}

		/**
		 * assert if the current user is verified
		 *
		 * @param \Exception|string|null $error_msg  the error message
		 * @param mixed                  $error_data the error data
		 *
		 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
		 * @throws string
		 */
		public static function assertUserVerified($error_msg = 'OZ_ERROR_YOU_MUST_LOGIN', $error_data = null)
		{
			if (!UsersUtils::userVerified()) {
				if (!self::isException($error_msg)) {
					$error_msg = new UnverifiedUserException($error_msg, $error_data);
				}

				throw $error_msg;
			}
		}

		/**
		 * assert if the current user is a verified admin
		 *
		 * @param \Exception|string|null $error_msg  the error message
		 * @param mixed                  $error_data the error data
		 *
		 * @throws \Gobl\DBAL\Exceptions\DBALException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \OZONE\OZ\Exceptions\ForbiddenException
		 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
		 */
		public static function assertIsAdmin($error_msg = 'OZ_ERROR_YOU_ARE_NOT_ADMIN', $error_data = null)
		{
			if (!UsersUtils::userVerified()) {
				if (!self::isException($error_msg)) {
					$error_msg = new UnverifiedUserException($error_msg, $error_data);
				}

				throw $error_msg;
			}

			if (!AdminUtils::isAdmin(UsersUtils::getCurrentUserId())) {
				if (!self::isException($error_msg)) {
					$error_msg = new ForbiddenException($error_msg, $error_data);
				}

				throw $error_msg;
			}
		}

		/**
		 * assert if the result of a given expression is evaluated to true
		 *
		 * @param mixed                  $expression the expression
		 * @param \Exception|string|null $error_msg  the error message
		 * @param mixed                  $error_data the error data
		 *
		 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
		 * @throws string
		 */
		public static function assertAuthorizeAction($expression, $error_msg = 'OZ_ERROR_NOT_ALLOWED', $error_data = null)
		{
			if (!$expression) {
				if (!self::isException($error_msg)) {
					$error_msg = new UnauthorizedActionException($error_msg, $error_data);
				}

				throw $error_msg;
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
		 * @param \Exception|string|null $error_msg       the error message
		 * @param mixed                  $error_data      the error data
		 *
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 * @throws string
		 */
		public static function assertForm($form, array $required_fields, $error_msg = 'OZ_ERROR_INVALID_FORM', $error_data = null)
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
				if (!self::isException($error_msg)) {
					$error_msg = new InvalidFormException($error_msg, $error_data);
				}

				throw $error_msg;
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