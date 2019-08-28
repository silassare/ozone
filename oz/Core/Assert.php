<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Core;

	use OZONE\OZ\Exceptions\InvalidFormException;
	use OZONE\OZ\Exceptions\UnauthorizedActionException;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class Assert
	{
		/**
		 * asserts if the result of a given expression is evaluated to true
		 *
		 * @param mixed                  $assertion the assertion
		 * @param \Exception|string|null $error_msg  the error message
		 * @param mixed                  $error_data the error data
		 *
		 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
		 * @throws string
		 */
		public static function assertAuthorizeAction($assertion, $error_msg = 'OZ_ERROR_NOT_ALLOWED', $error_data = null)
		{
			if (!$assertion) {
				if (!self::isException($error_msg)) {
					$error_msg = new UnauthorizedActionException($error_msg, $error_data);
				}

				throw $error_msg;
			}
		}

		/**
		 * asserts if a given result is an ozone error
		 *
		 * @param mixed $result the result
		 *
		 * @throws \Exception
		 */
		public static function assertOperationSuccess($result)
		{
			if (self::isException($result)) {
				throw $result;
			}
		}

		/**
		 * asserts if a given form contains has all required fields
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