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
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Users\UsersRepository;

/**
 * Class TypePhone.
 *
 * Validates E.164 phone numbers of the form `+<digits>` where the digit
 * portion is between 6 and 15 characters (ITU-T E.164), giving a total
 * maximum length of 16 characters (+ sign + 15 digits).
 *
 * @extends Type<mixed, null|string>
 */
class TypePhone extends Type
{
	public const NAME = 'phone';

	/**
	 * E.164 phone regex: `+` followed by 6 to 15 digits.
	 * Total maximum length: 16 characters (1 for `+`, up to 15 for digits).
	 */
	public const PHONE_REG = '~^\+\d{6,15}$~';

	/**
	 * TypePhone constructor.
	 *
	 * The underlying TypeString uses max=16 to match the regex maximum:
	 * `+` (1 char) + up to 15 digits = 16 chars total.
	 *
	 * @throws TypesException
	 */
	public function __construct()
	{
		parent::__construct(new TypeString(1, 16, self::PHONE_REG));
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
	#[Override]
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
			$subject->reject(new TypesInvalidValueException('OZ_FIELD_PHONE_INVALID', null, $e));

			return;
		}

		$debug = [
			'phone' => $value,
			'value' => $value,
		];

		if (!empty($value)) {
			$value         = \str_replace(' ', '', $value);
			$registered    = $this->getOption('registered');
			$registered_as = $this->getOption('registered_as');

			if (false === $registered && AuthUsers::identify($registered_as, $value, AuthUserInterface::IDENTIFIER_NAME_PHONE)) {
				$subject->reject(new TypesInvalidValueException('OZ_FIELD_PHONE_ALREADY_REGISTERED', $debug));

				return;
			}

			if (true === $registered && !AuthUsers::identify($registered_as, $value, AuthUserInterface::IDENTIFIER_NAME_PHONE)) {
				$subject->reject(new TypesInvalidValueException('OZ_FIELD_PHONE_NOT_REGISTERED', $debug));

				return;
			}
		}

		$subject->accept($value);
	}
}
