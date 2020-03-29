<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Columns\Types;

use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\TypeString;
use OZONE\OZ\User\UsersManager;

final class TypeCC2 extends TypeString
{
	const CC2_REG = '~^[a-zA-Z]{2}$~';

	private $authorized = false;

	/**
	 * @inheritdoc
	 */
	public static function getInstance(array $options)
	{
		$instance = new self();

		if (self::getOptionKey($options, 'authorized', false)) {
			$instance->authorized();
		}

		if (self::getOptionKey($options, 'null', false)) {
			$instance->nullAble();
		}

		if (\array_key_exists('default', $options)) {
			$instance->setDefault($options['default']);
		}

		return $instance;
	}

	/**
	 * TypeCC2 constructor.
	 *
	 * @inheritdoc
	 */
	public function __construct()
	{
		parent::__construct(2, 2, self::CC2_REG);
	}

	/**
	 * @return $this
	 */
	public function authorized()
	{
		$this->authorized = true;

		return $this;
	}

	/**
	 * @param $value
	 * @param $column_name
	 * @param $table_name
	 *
	 * @throws \Exception
	 *
	 * @return string
	 */
	public function validate($value, $column_name, $table_name)
	{
		$success = true;

		$debug = [
			'value' => $value,
		];

		try {
			$value = parent::validate($value, $column_name, $table_name);
		} catch (TypesInvalidValueException $e) {
			$success = false;
		}

		if (!$success) {
			throw new TypesInvalidValueException('OZ_FIELD_COUNTRY_UNKNOWN', $debug);
		}

		if (!empty($value)) {
			$value = \strtoupper($value); //<-- important
			if ($this->authorized) {
				if (!UsersManager::authorizedCountry($value)) {
					throw new TypesInvalidValueException('OZ_FIELD_COUNTRY_NOT_ALLOWED', $debug);
				}
			} elseif (!UsersManager::getCountryObject($value)) {
				throw new TypesInvalidValueException('OZ_FIELD_COUNTRY_UNKNOWN', $debug);
			}
		}

		return $value;
	}

	/**
	 * @inheritdoc
	 */
	public function getCleanOptions()
	{
		$options               = parent::getCleanOptions();
		$options['authorized'] = $this->authorized;

		return $options;
	}
}