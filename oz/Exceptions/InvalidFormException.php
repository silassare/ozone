<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Exceptions;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class InvalidFormException
	 *
	 * Use this exception when a field is mixing in
	 * a form or the form is somehow invalid
	 *
	 * @package OZONE\OZ\Exceptions
	 */
	class InvalidFormException extends BaseException
	{
		/**
		 * InvalidFormException constructor.
		 *
		 * @param string|null     $message  the exception message
		 * @param array|null      $data     additional exception data
		 * @param \Throwable|null $previous previous throwable used for the exception chaining
		 */
		public function __construct($message = null, array $data = null, \Throwable $previous = null)
		{
			parent::__construct((empty($message) ? 'OZ_ERROR_INVALID_FORM' : $message), BaseException::INVALID_FORM, $data, $previous);
		}
	}