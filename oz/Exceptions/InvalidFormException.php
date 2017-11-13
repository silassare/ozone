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
		 * @param string          $message  the exception message
		 * @param array|null      $data     additional exception data
		 * @param \Exception|null $previous previous exception if nested exception
		 */
		public function __construct($message = 'OZ_ERROR_INVALID_FORM', array $data = null, \Exception $previous = null)
		{
			parent::__construct($message, BaseException::INVALID_FORM, $data, $previous);
		}

		/**
		 * {@inheritdoc}
		 */
		public function procedure()
		{
			$this->informClient();
		}
	}