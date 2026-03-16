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
use Gobl\DBAL\Types\Interfaces\ValidationSubjectInterface;
use Gobl\DBAL\Types\Type;
use Gobl\DBAL\Types\TypeString;
use Override;
use OZONE\Core\App\Settings;

/**
 * Class TypeGender.
 *
 * @extends Type<mixed, null|string>
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
	#[Override]
	public static function getInstance(array $options): static
	{
		return (new static())->configure($options);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function default($default): static
	{
		$this->base_type->default($default);

		return parent::default($default);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	protected function runValidation(ValidationSubjectInterface $subject): void
	{
		$value = $subject->getUnsafeValue();

		try {
			$value = $this->base_type->validate($value)->getCleanValue();
		} catch (TypesInvalidValueException $e) {
			$subject->reject(new TypesInvalidValueException('OZ_FIELD_GENDER_INVALID', null, $e));

			return;
		}

		$debug = [
			'value' => $value,
		];

		if (!empty($value)) {
			$allowed = Settings::get('oz.users', 'OZ_USER_ALLOWED_GENDERS');

			if (!\in_array($value, $allowed, true)) {
				$subject->reject(new TypesInvalidValueException('OZ_FIELD_GENDER_INVALID', $debug));

				return;
			}
		}

		$subject->accept($value);
	}
}
