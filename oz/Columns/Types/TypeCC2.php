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

use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Type;
use Gobl\DBAL\Types\TypeString;
use OZONE\Core\Users\Countries;

/**
 * Class TypeCC2.
 */
class TypeCC2 extends Type
{
	public const NAME = 'cc2';

	public const CC2_REG = '~^[a-zA-Z]{2}$~';

	/**
	 * TypeCC2 constructor.
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function __construct()
	{
		parent::__construct(new TypeString(2, 2, self::CC2_REG));
	}

	/**
	 * {@inheritDoc}
	 */
	public static function getInstance(array $options): static
	{
		return (new self())->configure($options);
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
	 * @return $this
	 */
	public function authorized(bool $authorized = true): static
	{
		return $this->setOption('authorized', $authorized);
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
			throw new TypesInvalidValueException('OZ_FIELD_COUNTRY_INVALID', $debug, $e);
		}

		if (!empty($value)) {
			$value = \strtoupper($value);

			if ($this->getOption('authorized')) {
				if (!Countries::allowed($value)) {
					throw new TypesInvalidValueException('OZ_FIELD_COUNTRY_NOT_ALLOWED', $debug);
				}
			} elseif (!Countries::get($value)) {
				throw new TypesInvalidValueException('OZ_FIELD_COUNTRY_UNKNOWN', $debug);
			}
		}

		return $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function configure(array $options): static
	{
		if (isset($options['authorized'])) {
			$this->authorized((bool) $options['authorized']);
		}

		return parent::configure($options);
	}
}
