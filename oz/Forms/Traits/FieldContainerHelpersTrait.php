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
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeBool;
use Gobl\DBAL\Types\TypeDate;
use Gobl\DBAL\Types\TypeDecimal;
use Gobl\DBAL\Types\TypeEnum;
use Gobl\DBAL\Types\TypeFloat;
use Gobl\DBAL\Types\TypeInt;
use Gobl\DBAL\Types\TypeJson;
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
 * Trait FieldContainerHelpersTrait.
 */
trait FieldContainerHelpersTrait
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
		return $this->withType($name, new TypeString(), $required);
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
		return $this->withType($name, new TypeBigint(), $required);
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
		return $this->withType($name, new TypeInt(), $required);
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
		return $this->withType($name, new TypeDecimal(), $required);
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
		return $this->withType($name, new TypeFloat(), $required);
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
		return $this->withType($name, new TypeEnum($enum_class), $required);
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
		return $this->withType($name, new TypeBool(), $required);
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
		return $this->withType($name, new TypeDate(), $required);
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
		return $this->withType($name, new TypeList(), $required);
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
		return $this->withType($name, new TypeMap(), $required);
	}

	/**
	 * Creates a new field of type json.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return TypeJson
	 */
	public function json(string $name, bool $required = false): TypeJson
	{
		return $this->withType($name, new TypeJson(), $required);
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
		return $this->withType($name, new TypesSwitcher(), $required);
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
		return $this->withType($name, new TypeCC2(), $required);
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
		return $this->withType($name, new TypeEmail(), $required);
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
		return $this->withType($name, new TypeFile(), $required);
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
		return $this->withType($name, new TypeGender(), $required);
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
		return $this->withType($name, new TypePhone(), $required);
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
		return $this->withType($name, new TypePassword(), $required);
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
		return $this->withType($name, new TypeUrl(), $required);
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
		return $this->withType($name, new TypeUsername(), $required);
	}

	/**
	 * Helper method to create a field with a given type and required flag.
	 *
	 * @template T of TypeInterface|TypesSwitcher
	 *
	 * @param string $name
	 * @param T      $type
	 * @param bool   $required
	 *
	 * @return T
	 */
	private function withType(string $name, TypeInterface|TypesSwitcher $type, bool $required = false): TypeInterface|TypesSwitcher
	{
		$field = $this->field($name)->type($type);

		if ($required) {
			$field->required();
		}

		return $type;
	}
}
