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

	class OZoneNotFoundException extends OZoneBaseException
	{

		/**
		 * OZoneNotFoundException constructor.
		 *
		 * @param string     $message the exception message
		 * @param array|null $data    additional exception data
		 */
		public function __construct($message = 'OZ_ERROR_NOT_FOUND', array $data = null)
		{
			if (empty($data)) {
				$data = [$_SERVER['REQUEST_URI']];
			}

			parent::__construct($message, OZoneBaseException::NOT_FOUND, $data);
		}

		/**
		 * {@inheritdoc}
		 */
		public function procedure()
		{
			$this->informClient();
		}
	}