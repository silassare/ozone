<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Db\Types;

	interface Type
	{
		const TYPE_INT    = 1;
		const TYPE_BIGINT = 2;
		const TYPE_FLOAT  = 3;
		const TYPE_BOOL   = 4;
		const TYPE_STRING = 5;

		/**
		 * check if type is int, bigint, string ...
		 *
		 * @param int $type_const type constant
		 *
		 * @return bool
		 */
		public function is($type_const);

		/**
		 * explicitly set the default value.
		 *
		 * the default should comply with all rules or not ?
		 * the answer is up to you.
		 *
		 * @param mixed $value the value to use as default
		 *
		 * @return \OZONE\OZ\Db\Types\Type
		 */
		public function def($value);

		/**
		 * enable null value.
		 *
		 * @return \OZONE\OZ\Db\Types\Type
		 */
		public function nullable();

		/**
		 * called to validate a form field value.
		 *
		 * @param string $value the value to validate
		 *
		 * @return mixed    the cleaned value to use.
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneInvalidFieldException    when user input is invalid
		 */
		public function validate($value);

		/**
		 * get clean options from type instance.
		 *
		 * @return array
		 */
		public function getCleanOptions();

		/**
		 * construct type instance based on given options.
		 *
		 * @param array $options the options
		 *
		 * @return \OZONE\OZ\Db\Types\Type
		 *
		 * @throws \Exception    when options is invalid
		 */
		public static function getInstance(array $options);
	}