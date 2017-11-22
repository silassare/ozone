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
	 * Class NotFoundException
	 *
	 * @package OZONE\OZ\Exceptions
	 */
	class NotFoundException extends BaseException
	{

		/**
		 * NotFoundException constructor.
		 *
		 * @param string          $message  the exception message
		 * @param array|null      $data     additional exception data
		 * @param null|\Throwable $previous previous exception if nested exception
		 */
		public function __construct($message = 'OZ_ERROR_NOT_FOUND', array $data = null, \Throwable $previous = null)
		{
			if (empty($data)) {
				$data = ['uri' => $_SERVER['REQUEST_URI']];
			}

			parent::__construct($message, BaseException::NOT_FOUND, $data, $previous);
		}

		/**
		 * {@inheritdoc}
		 */
		public function procedure()
		{
			$this->informClient();
		}
	}