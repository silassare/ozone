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
use OZONE\Core\Forms\Field;
use OZONE\Core\Forms\TypesSwitcher;

/**
 * Trait FieldContainerHelpersTrait.
 *
 * Every helper returns the {@see Field}, so field-level configuration chains
 * directly. Configure the type with {@see Field::configureType()}:
 *
 * ```php
 * $this->string('name', true)
 *     ->configureType(static fn (TypeString $t) => $t->min(2)->max(60))
 *     ->label('Full name');
 * ```
 */
trait FieldContainerHelpersTrait
{
	/**
	 * Creates a new field of type string.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return Field
	 */
	public function string(string $name, bool $required = false): Field
	{
		return $this->withType($name, new TypeString(), $required);
	}

	/**
	 * Creates a new field of type bigint.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return Field
	 */
	public function bigint(string $name, bool $required = false): Field
	{
		return $this->withType($name, new TypeBigint(), $required);
	}

	/**
	 * Creates a new field of type int.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return Field
	 */
	public function int(string $name, bool $required = false): Field
	{
		return $this->withType($name, new TypeInt(), $required);
	}

	/**
	 * Creates a new field of type decimal.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return Field
	 */
	public function decimal(string $name, bool $required = false): Field
	{
		return $this->withType($name, new TypeDecimal(), $required);
	}

	/**
	 * Creates a new field of type float.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return Field
	 */
	public function float(string $name, bool $required = false): Field
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
	 * @return Field
	 *
	 * @throws TypesException
	 */
	public function enum(string $name, string $enum_class, bool $required = false): Field
	{
		return $this->withType($name, new TypeEnum($enum_class), $required);
	}

	/**
	 * Creates a new field of type bool.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return Field
	 */
	public function bool(string $name, bool $required = false): Field
	{
		return $this->withType($name, new TypeBool(), $required);
	}

	/**
	 * Creates a new field of type date.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return Field
	 */
	public function date(string $name, bool $required = false): Field
	{
		return $this->withType($name, new TypeDate(), $required);
	}

	/**
	 * Creates a new field of type date formatted as timestamp.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return Field
	 */
	public function timestamp(string $name, bool $required = false): Field
	{
		return $this->withType($name, (new TypeDate())->format('timestamp'), $required);
	}

	/**
	 * Creates a new field of type list.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return Field
	 */
	public function list(string $name, bool $required = false): Field
	{
		return $this->withType($name, new TypeList(), $required);
	}

	/**
	 * Creates a new field of type map.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return Field
	 */
	public function map(string $name, bool $required = false): Field
	{
		return $this->withType($name, new TypeMap(), $required);
	}

	/**
	 * Creates a new field of type json.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return Field
	 */
	public function json(string $name, bool $required = false): Field
	{
		return $this->withType($name, new TypeJson(), $required);
	}

	/**
	 * Creates a new field of type switcher.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return Field
	 */
	public function switcher(string $name, bool $required = false): Field
	{
		return $this->withType($name, new TypesSwitcher(), $required);
	}

	/**
	 * Creates a new field of type cc2.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return Field
	 */
	public function cc2(string $name, bool $required = false): Field
	{
		return $this->withType($name, new TypeCC2(), $required);
	}

	/**
	 * Creates a new field of type email.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return Field
	 */
	public function email(string $name, bool $required = false): Field
	{
		return $this->withType($name, new TypeEmail(), $required);
	}

	/**
	 * Creates a new field of type file.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return Field
	 */
	public function file(string $name, bool $required = false): Field
	{
		return $this->withType($name, new TypeFile(), $required);
	}

	/**
	 * Creates a new field of type gender.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return Field
	 */
	public function gender(string $name, bool $required = false): Field
	{
		return $this->withType($name, new TypeGender(), $required);
	}

	/**
	 * Creates a new field of type phone.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return Field
	 */
	public function phone(string $name, bool $required = false): Field
	{
		return $this->withType($name, new TypePhone(), $required);
	}

	/**
	 * Creates a new field of type password.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return Field
	 */
	public function password(string $name, bool $required = false): Field
	{
		return $this->withType($name, new TypePassword(), $required);
	}

	/**
	 * Creates a new field of type url.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return Field
	 */
	public function url(string $name, bool $required = false): Field
	{
		return $this->withType($name, new TypeUrl(), $required);
	}

	/**
	 * Creates a new field of type user name.
	 *
	 * @param string $name
	 * @param bool   $required
	 *
	 * @return Field
	 */
	public function username(string $name, bool $required = false): Field
	{
		return $this->withType($name, new TypeUsername(), $required);
	}

	/**
	 * Helper method to create a field with a given type and required flag.
	 *
	 * @param string                      $name
	 * @param TypeInterface|TypesSwitcher $type
	 * @param bool                        $required
	 *
	 * @return Field
	 */
	private function withType(
		string $name,
		TypeInterface|TypesSwitcher $type,
		bool $required = false
	): Field {
		$field = $this->field($name)->type($type);

		if ($required) {
			$field->required();
		}

		return $field;
	}
}
