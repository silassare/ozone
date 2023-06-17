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
use OZONE\Core\Users\Users;

/**
 * Class TypePhone.
 */
class TypePhone extends Type
{
	public const NAME = 'phone';

	public const PHONE_REG = '~^\+\d{6,15}$~';

	/**
	 * TypePhone constructor.
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function __construct()
	{
		parent::__construct(new TypeString(1, 15, self::PHONE_REG));
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
	public function default($default): self
	{
		$this->base_type->default($default);

		return parent::default($default);
	}

	/**
	 * To accept phone number that are registered only.
	 *
	 * @return $this
	 */
	public function registered(): self
	{
		return $this->setOption('registered', true);
	}

	/**
	 * To accept phone number that are not registered only.
	 *
	 * @return $this
	 */
	public function notRegistered(): self
	{
		return $this->setOption('registered', false);
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate($value): ?string
	{
		$debug = [
			'phone' => $value,
			'value' => $value,
		];

		if (\is_string($value)) {
			$value = \str_replace(' ', '', $value);
		}

		try {
			$value = $this->base_type->validate($value);
		} catch (TypesInvalidValueException $e) {
			throw new TypesInvalidValueException('OZ_FIELD_PHONE_INVALID', $debug, $e);
		}

		if (!empty($value)) {
			$registered = $this->getOption('registered');

			if (false === $registered && Users::withPhone($value)) {
				throw new TypesInvalidValueException('OZ_FIELD_PHONE_ALREADY_REGISTERED', $debug);
			}

			if (true === $registered && !Users::withPhone($value)) {
				throw new TypesInvalidValueException('OZ_FIELD_PHONE_NOT_REGISTERED', $debug);
			}
		}

		return $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function configure(array $options): Type
	{
		if (isset($options['registered'])) {
			if ($options['registered']) {
				$this->registered();
			} else {
				$this->notRegistered();
			}
		}

		return parent::configure($options);
	}
}
