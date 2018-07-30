<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Exceptions;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class UnverifiedUserException
	 *
	 * @package OZONE\OZ\Exceptions
	 */
	class UnverifiedUserException extends BaseException
	{

		/**
		 * UnverifiedUserException constructor.
		 *
		 * @param string|null          $message  the exception message
		 * @param array|null      $data     additional exception data
		 * @param null|\Throwable $previous previous exception if nested exception
		 */
		public function __construct($message = null, array $data = null, \Throwable $previous = null)
		{
			parent::__construct((empty($message)? 'OZ_ERROR_YOU_MUST_LOGIN' : $message), BaseException::UNVERIFIED_USER, $data, $previous);
		}

		/**
		 * {@inheritdoc}
		 */
		public function procedure()
		{
			$this->informClient();
		}
	}