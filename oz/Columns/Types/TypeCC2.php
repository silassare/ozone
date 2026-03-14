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
use OZONE\Core\Users\Countries;

/**
 * Class TypeCC2.
 *
 * @extends Type<mixed, null|string>
 */
class TypeCC2 extends Type
{
	public const NAME = 'cc2';

	public const CC2_REG = '~^[a-zA-Z]{2}$~';

	/**
	 * TypeCC2 constructor.
	 *
	 * @throws TypesException
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
	 * Only allow authorized countries.
	 *
	 * @return $this
	 */
	public function authorized(bool $authorized = true): static
	{
		return $this->setOption('authorized', $authorized);
	}

	/**
	 * Only allow countries that are in the database.
	 *
	 * @return $this
	 */
	public function check(bool $check = true): static
	{
		return $this->setOption('check', $check);
	}

	/**
	 * {@inheritDoc}
	 */
	public function configure(array $options): static
	{
		if (isset($options['authorized'])) {
			$this->authorized((bool) $options['authorized']);
		}
		if (isset($options['check'])) {
			$this->check((bool) $options['check']);
		}

		return parent::configure($options);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function runValidation(ValidationSubjectInterface $subject): void
	{
		$value = $subject->getUnsafeValue();

		try {
			$value = $this->base_type->validate($value)->getCleanValue();
		} catch (TypesInvalidValueException $e) {
			$subject->reject(new TypesInvalidValueException('OZ_FIELD_COUNTRY_INVALID', null, $e));

			return;
		}

		$debug = [
			'value' => $value,
		];

		if (!empty($value)) {
			$value = \strtoupper($value);

			if ($this->getOption('authorized')) {
				if (!Countries::allowed($value)) {
					$subject->reject(new TypesInvalidValueException('OZ_FIELD_COUNTRY_NOT_ALLOWED', $debug));

					return;
				}
			} elseif ($this->getOption('check')) {
				if (!Countries::get($value)) {
					$subject->reject(new TypesInvalidValueException('OZ_FIELD_COUNTRY_UNKNOWN', $debug));

					return;
				}
			}
		}

		$subject->accept($value);
	}
}
