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

namespace OZONE\OZ\Columns\Types;

use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Type;
use Gobl\DBAL\Types\TypeString;
use OZONE\OZ\Core\Configs;

/**
 * Class TypePassword.
 */
class TypePassword extends Type
{
	public const NAME = 'password';

	/**
	 * TypePassword constructor.
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function __construct()
	{
		parent::__construct(new TypeString(1, 255));
	}

	/**
	 * {@inheritDoc}
	 */
	public static function getInstance(array $options): self
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
	public function setDefault($default): self
	{
		$this->base_type->setDefault($default);

		return parent::setDefault($default);
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate($value): ?string
	{
		$min   = Configs::get('oz.users', 'OZ_USER_PASS_MIN_LENGTH');
		$max   = Configs::get('oz.users', 'OZ_USER_PASS_MAX_LENGTH');
		$debug = [
			'value' => $value,
			'min'   => $min,
			'max'   => $max,
		];

		try {
			$value = $this->base_type->validate($value);
		} catch (TypesInvalidValueException $e) {
			throw new TypesInvalidValueException('OZ_FIELD_PASS_INVALID', $debug, $e);
		}

		if (!empty($value)) {
			$value = (string) $value;
			$len   = \strlen($value);

			if ($len < $min) {
				throw new TypesInvalidValueException('OZ_FIELD_PASS_TOO_SHORT', $debug);
			}

			if ($len > $max) {
				throw new TypesInvalidValueException('OZ_FIELD_PASS_TOO_LONG', $debug);
			}
		}

		return $value;
	}
}
