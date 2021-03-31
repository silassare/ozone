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

final class TypeEmail extends TypeString
{
	private $registered;

	/**
	 * TypeEmail constructor.
	 *
	 * @inheritdoc
	 */
	public function __construct()
	{
		parent::__construct(1, 255);
	}

	/**
	 * To accept email that are not registered only
	 *
	 * @return $this
	 */
	public function registered()
	{
		$this->registered = true;

		return $this;
	}

	/**
	 * To accept email that are not registered only
	 *
	 * @return $this
	 */
	public function notRegistered()
	{
		$this->registered = false;

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
		$debug = [
			'email' => $value,
			'value' => $value,
		];

		try {
			$value = parent::validate($value, $column_name, $table_name);
		} catch (TypesInvalidValueException $e) {
			throw new TypesInvalidValueException('OZ_FIELD_EMAIL_INVALID', $debug, $e);
		}

		if (!empty($value)) {
			if (!\filter_var($value, \FILTER_VALIDATE_EMAIL)) {
				throw new TypesInvalidValueException('OZ_FIELD_EMAIL_INVALID', $debug);
			}

			if ($this->registered === false && UsersManager::searchUserWithEmail($value)) {
				throw new TypesInvalidValueException('OZ_FIELD_EMAIL_ALREADY_REGISTERED', $debug);
			}

			if ($this->registered === true && !UsersManager::searchUserWithEmail($value)) {
				throw new TypesInvalidValueException('OZ_FIELD_EMAIL_NOT_REGISTERED', $debug);
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
