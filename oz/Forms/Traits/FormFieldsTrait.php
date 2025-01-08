<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace OZONE\Core\Forms\Traits;

use BackedEnum;
use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeBool;
use Gobl\DBAL\Types\TypeDate;
use Gobl\DBAL\Types\TypeDecimal;
use Gobl\DBAL\Types\TypeEnum;
use Gobl\DBAL\Types\TypeFloat;
use Gobl\DBAL\Types\TypeInt;
use Gobl\DBAL\Types\TypeList;
use Gobl\DBAL\Types\TypeMap;
use Gobl\DBAL\Types\TypeString;
use OZONE\Core\Columns\Types\TypeCC2;
use OZONE\Core\Columns\Types\TypeEmail;
use OZONE\Core\Columns\Types\TypeFile;
use OZONE\Core\Columns\Types\TypeGender;
use OZONE\Core\Columns\Types\TypePassword;
use OZONE\Core\Columns\Types\TypePhone;
use OZONE\Core\Columns\Types\TypeUrl;
use OZONE\Core\Columns\Types\TypeUsername;
use OZONE\Core\Forms\TypesSwitcher;

/**
 * Trait FormFieldsTrait.
 */
trait FormFieldsTrait
{
	/**
	 * Creates a new field of type string.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return TypeString
	 */
	public function string(string $name, bool $required = false): TypeString
	{
		$type  = new TypeString();
		$field = $this->field($name)->type($type);

		if ($required) {
			$field->required();
		}

		return $type;
	}

	/**
	 * Creates a new field of type bigint.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return TypeBigint
	 */
	public function bigint(string $name, bool $required = false): TypeBigint
	{
		$type  = new TypeBigint();
		$field = $this->field($name)->type($type);

		if ($required) {
			$field->required();
		}

		return $type;
	}

	/**
	 * Creates a new field of type int.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return TypeInt
	 */
	public function int(string $name, bool $required = false): TypeInt
	{
		$type  = new TypeInt();
		$field = $this->field($name)->type($type);

		if ($required) {
			$field->required();
		}

		return $type;
	}

	/**
	 * Creates a new field of type decimal.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return TypeDecimal
	 */
	public function decimal(string $name, bool $required = false): TypeDecimal
	{
		$type  = new TypeDecimal();
		$field = $this->field($name)->type($type);

		if ($required) {
			$field->required();
		}

		return $type;
	}

	/**
	 * Creates a new field of type float.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return TypeFloat
	 */
	public function float(string $name, bool $required = false): TypeFloat
	{
		$type  = new TypeFloat();
		$field = $this->field($name)->type($type);

		if ($required) {
			$field->required();
		}

		return $type;
	}

	/**
	 * Creates a new field of type enum.
	 *
	 * @param string                   $name
	 * @param class-string<BackedEnum> $enum_class
	 * @param bool                     $required
	 *
	 * @return TypeEnum
	 *
	 * @throws TypesException
	 */
	public function enum(string $name, string $enum_class, bool $required = false): TypeEnum
	{
		$type  = new TypeEnum($enum_class);
		$field = $this->field($name)->type($type);

		if ($required) {
			$field->required();
		}

		return $type;
	}

	/**
	 * Creates a new field of type bool.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return TypeBool
	 */
	public function bool(string $name, bool $required = false): TypeBool
	{
		$type  = new TypeBool();
		$field = $this->field($name)->type($type);

		if ($required) {
			$field->required();
		}

		return $type;
	}

	/**
	 * Creates a new field of type date.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return TypeDate
	 */
	public function date(string $name, bool $required = false): TypeDate
	{
		$type  = new TypeDate();
		$field = $this->field($name)->type($type);

		if ($required) {
			$field->required();
		}

		return $type;
	}

	/**
	 * Creates a new field of type date formatted as timestamp.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return TypeDate
	 */
	public function timestamp(string $name, bool $required = false): TypeDate
	{
		return $this->date($name, $required)->format('timestamp');
	}

	/**
	 * Creates a new field of type list.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return TypeList
	 */
	public function list(string $name, bool $required = false): TypeList
	{
		$type  = new TypeList();
		$field = $this->field($name)->type($type);

		if ($required) {
			$field->required();
		}

		return $type;
	}

	/**
	 * Creates a new field of type map.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return TypeMap
	 */
	public function map(string $name, bool $required = false): TypeMap
	{
		$type  = new TypeMap();
		$field = $this->field($name)->type($type);

		if ($required) {
			$field->required();
		}

		return $type;
	}

	/**
	 * Creates a new field of type switcher.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return TypesSwitcher
	 */
	public function switcher(string $name, bool $required = false): TypesSwitcher
	{
		$type  = new TypesSwitcher();
		$field = $this->field($name)->type($type);

		if ($required) {
			$field->required();
		}

		return $type;
	}

	/**
	 * Creates a new field of type cc2.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return TypeCC2
	 */
	public function cc2(string $name, bool $required = false): TypeCC2
	{
		$type  = new TypeCC2();
		$field = $this->field($name)->type($type);

		if ($required) {
			$field->required();
		}

		return $type;
	}

	/**
	 * Creates a new field of type email.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return TypeEmail
	 */
	public function email(string $name, bool $required = false): TypeEmail
	{
		$type  = new TypeEmail();
		$field = $this->field($name)->type($type);

		if ($required) {
			$field->required();
		}

		return $type;
	}

	/**
	 * Creates a new field of type file.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return TypeFile
	 */
	public function file(string $name, bool $required = false): TypeFile
	{
		$type  = new TypeFile();
		$field = $this->field($name)->type($type);

		if ($required) {
			$field->required();
		}

		return $type;
	}

	/**
	 * Creates a new field of type gender.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return TypeGender
	 */
	public function gender(string $name, bool $required = false): TypeGender
	{
		$type  = new TypeGender();
		$field = $this->field($name)->type($type);

		if ($required) {
			$field->required();
		}

		return $type;
	}

	/**
	 * Creates a new field of type phone.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return TypePhone
	 */
	public function phone(string $name, bool $required = false): TypePhone
	{
		$type  = new TypePhone();
		$field = $this->field($name)->type($type);

		if ($required) {
			$field->required();
		}

		return $type;
	}

	/**
	 * Creates a new field of type password.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return TypePassword
	 */
	public function password(string $name, bool $required = false): TypePassword
	{
		$type  = new TypePassword();
		$field = $this->field($name)->type($type);

		if ($required) {
			$field->required();
		}

		return $type;
	}

	/**
	 * Creates a new field of type url.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return TypeUrl
	 */
	public function url(string $name, bool $required = false): TypeUrl
	{
		$type  = new TypeUrl();
		$field = $this->field($name)->type($type);

		if ($required) {
			$field->required();
		}

		return $type;
	}

	/**
	 * Creates a new field of type user name.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return TypeUsername
	 */
	public function username(string $name, bool $required = false): TypeUsername
	{
		$type  = new TypeUsername();
		$field = $this->field($name)->type($type);

		if ($required) {
			$field->required();
		}

		return $type;
	}
}
