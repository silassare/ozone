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

namespace OZONE\Core\Columns\Types;

use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Type;
use Gobl\DBAL\Types\TypeString;
use OZONE\Core\App\Settings;

/**
 * Class TypeGender.
 */
class TypeGender extends Type
{
	public const NAME = 'gender';

	/**
	 * TypeGender constructor.
	 *
	 * @throws TypesException
	 */
	public function __construct()
	{
		parent::__construct(new TypeString(1, 30));
	}

	/**
	 * {@inheritDoc}
	 */
	public static function getInstance(array $options): static
	{
		return (new static())->configure($options);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * {@inheritDoc}
	 */
	public function default($default): static
	{
		$this->base_type->default($default);

		return parent::default($default);
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate($value): ?string
	{
		$debug = [
			'value' => $value,
		];

		try {
			$value = $this->base_type->validate($value);
		} catch (TypesInvalidValueException $e) {
			throw new TypesInvalidValueException('OZ_FIELD_GENDER_INVALID', $debug, $e);
		}

		if (!empty($value)) {
			$allowed = Settings::get('oz.users', 'OZ_USER_ALLOWED_GENDERS');

			if (!\in_array($value, $allowed, true)) {
				throw new TypesInvalidValueException('OZ_FIELD_GENDER_INVALID', $debug);
			}
		}

		return $value;
	}
}
