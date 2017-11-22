<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Exceptions;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class InvalidFieldException
	 *
	 * Use this exception when a field is invalid
	 *
	 * @package OZONE\OZ\Exceptions
	 */
	class InvalidFieldException extends BaseException
	{

		/**
		 * InvalidFieldException constructor.
		 *
		 * @param string          $message  the exception message
		 * @param array|null      $data     additional exception data
		 * @param null|\Throwable $previous previous exception if nested exception
		 */
		public function __construct($message = 'OZ_ERROR_INVALID_FIELD', array $data = null, \Throwable $previous = null)
		{
			parent::__construct($message, BaseException::INVALID_FIELD, $data, $previous);
		}

		/**
		 * {@inheritdoc}
		 */
		public function procedure()
		{
			$this->informClient();
		}
	}