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
	 * Class ForbiddenException
	 *
	 * @package OZONE\OZ\Exceptions
	 */
	class ForbiddenException extends BaseException
	{

		/**
		 * ForbiddenException constructor.
		 *
		 * @param string|null          $message  the exception message
		 * @param array|null      $data     additional exception data
		 * @param null|\Throwable $previous previous exception if nested exception
		 */
		public function __construct($message = null, array $data = null, \Throwable $previous = null)
		{
			parent::__construct((empty($message)? 'OZ_ERROR_NOT_ALLOWED' : $message), BaseException::FORBIDDEN, $data, $previous);
		}

		/**
		 * {@inheritdoc}
		 */
		public function procedure()
		{
			$this->informClient();
		}
	}