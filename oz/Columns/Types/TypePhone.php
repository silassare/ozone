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
use OZONE\OZ\User\UsersManager;

final class TypePhone extends TypeString
{
	const PHONE_REG = '~^\+\d{6,15}$~';

	private $registered;

	/**
	 * TypePhone constructor.
	 *
	 * @inheritdoc
	 */
	public function __construct()
	{
		parent::__construct(1, 15, self::PHONE_REG);
	}

	/**
	 * To accept phone number that are not registered only
	 *
	 * @return $this
	 */
	public function registered()
	{
		$this->registered = true;

		return $this;
	}

	/**
	 * To accept phone number that are not registered only
	 *
	 * @return $this
	 */
	public function notRegistered()
	{
		$this->registered = false;

		return $this;
	}

	/**
	 * @inheritdoc
	 *
	 * @param $value
	 * @param $column_name
	 * @param $table_name
	 *
	 * @throws \Exception
	 *
	 * @return null|mixed|string
	 */
	public function validate($value, $column_name, $table_name)
	{
		$debug = [
			'phone' => $value,
			'value' => $value,
		];

		if (\is_string($value)) {
			$value = \str_replace(' ', '', $value);
		}

		try {
			$value = parent::validate($value, $column_name, $table_name);
		} catch (TypesInvalidValueException $e) {
			throw new TypesInvalidValueException('OZ_FIELD_PHONE_INVALID', $debug, $e);
		}

		if (!empty($value)) {
			if ($this->registered === false && UsersManager::searchUserWithPhone($value)) {
				throw new TypesInvalidValueException('OZ_FIELD_PHONE_ALREADY_REGISTERED', $debug);
			}

			if ($this->registered === true && !UsersManager::searchUserWithPhone($value)) {
				throw new TypesInvalidValueException('OZ_FIELD_PHONE_NOT_REGISTERED', $debug);
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
		$options['registered'] = $this->registered;

		return $options;
	}

	/**
	 * @inheritdoc
	 */
	public static function getInstance(array $options)
	{
		$instance = new self();

		if (isset($options['registered'])) {
			$registered = $options['registered'];

			if ($registered === true) {
				$instance->registered();
			} else {
				$instance->notRegistered();
			}
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
