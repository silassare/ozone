<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Columns\Types;

use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\TypeString;
use OZONE\OZ\Ofv\OFormUtils;

final class TypeDate extends TypeString
{
	private $birth_date = false;

	private $min_age = 0;

	private $max_age = \PHP_INT_MAX;

	/**
	 * TypeDate constructor.
	 *
	 * @inheritdoc
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * @return $this
	 */
	public function birthDate()
	{
		$this->birth_date = true;

		return $this;
	}

	/**
	 * Sets age range.
	 *
	 * @param int $min the minimum age
	 * @param int $max the maximum age
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 *
	 * @return $this
	 */
	public function ageRange($min, $max)
	{
		self::assertSafeIntRange($min, $max, 1);

		$this->min_age = $min;
		$this->max_age = $max;

		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function validate($value, $column_name, $table_name)
	{
		$debug = [
			'value' => $value,
		];

		try {
			$value = parent::validate($value, $column_name, $table_name);
		} catch (TypesInvalidValueException $e) {
			throw new TypesInvalidValueException('OZ_FIELD_DATE_INVALID', $debug, $e);
		}

		if (!empty($value)) {
			if ($this->birth_date) {
				if (OFormUtils::isBirthDate($value, $this->min_age, $this->max_age)) {
					$format = OFormUtils::parseDate($value);
					$value  = $format['YYYY-MM-DD'];
				} else {
					throw new TypesInvalidValueException('OZ_FIELD_BIRTH_DATE_INVALID', $debug);
				}
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
		$options['birth_date'] = $this->birth_date;
		$options['min_age']    = $this->min_age;
		$options['max_age']    = $this->max_age;

		return $options;
	}

	/**
	 * @inheritdoc
	 */
	public static function getInstance(array $options)
	{
		$instance = new self();

		$instance->length(1, 10);

		if (self::getOptionKey($options, 'birth_date', false)) {
			$instance->birthDate();

			$min_age = self::getOptionKey($options, 'min_age', 1);
			$max_age = self::getOptionKey($options, 'max_age', \PHP_INT_MAX);

			$instance->ageRange($min_age, $max_age);
		}

		if (self::getOptionKey($options, 'null', false)) {
			$instance->nullAble();
		}

		if (\array_key_exists('default', $options)) {
			$instance->setDefault($options['default']);
		}

		return $instance;
	}
}
