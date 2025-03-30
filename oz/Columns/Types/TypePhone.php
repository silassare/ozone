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
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Users\UsersRepository;

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
	 * @throws TypesException
	 */
	public function __construct()
	{
		parent::__construct(new TypeString(1, 15, self::PHONE_REG));
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
	 * To accept phone number that are registered only.
	 *
	 * @return $this
	 */
	public function registered(string $as): static
	{
		return $this->setOption('registered', true)->setOption('registered_as', $as);
	}

	/**
	 * To accept phone number that are not registered only.
	 *
	 * @return $this
	 */
	public function notRegistered(string $as): static
	{
		return $this->setOption('registered', false)->setOption('registered_as', $as);
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
			$registered    = $this->getOption('registered');
			$registered_as = $this->getOption('registered_as');

			if (false === $registered && AuthUsers::identify($registered_as, $value, AuthUserInterface::IDENTIFIER_NAME_PHONE)) {
				throw new TypesInvalidValueException('OZ_FIELD_PHONE_ALREADY_REGISTERED', $debug);
			}

			if (true === $registered && !AuthUsers::identify($registered_as, $value, AuthUserInterface::IDENTIFIER_NAME_PHONE)) {
				throw new TypesInvalidValueException('OZ_FIELD_PHONE_NOT_REGISTERED', $debug);
			}
		}

		return $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function configure(array $options): static
	{
		if (isset($options['registered'])) {
			if ($options['registered']) {
				$this->registered($options['registered_as'] ?? UsersRepository::DEFAULT_USER_TYPE);
			} else {
				$this->notRegistered($options['registered_as'] ?? UsersRepository::DEFAULT_USER_TYPE);
			}
		}

		return parent::configure($options);
	}
}
