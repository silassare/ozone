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
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Users\UsersRepository;

/**
 * Class TypeEmail.
 *
 * @extends Type<mixed, null|string>
 */
class TypeEmail extends Type
{
	public const NAME = 'email';

	/**
	 * TypeEmail constructor.
	 *
	 * @throws TypesException
	 */
	public function __construct()
	{
		parent::__construct(new TypeString(1, 255));
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
	 * To accept email that are registered only.
	 *
	 * @return $this
	 */
	public function registered(string $as): static
	{
		return $this->setOption('registered', true)->setOption('registered_as', $as);
	}

	/**
	 * To accept email that are not registered only.
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
	protected function runValidation(ValidationSubjectInterface $subject): void
	{
		$value = $subject->getUnsafeValue();

		try {
			$value = $this->base_type->validate($value)->getCleanValue();
		} catch (TypesInvalidValueException $e) {
			$subject->reject(new TypesInvalidValueException('OZ_FIELD_EMAIL_INVALID', null, $e));

			return;
		}

		$debug = [
			'value' => $value,
		];

		if (!empty($value)) {
			if (!\filter_var($value, \FILTER_VALIDATE_EMAIL)) {
				$subject->reject(new TypesInvalidValueException('OZ_FIELD_EMAIL_INVALID', $debug));

				return;
			}

			$registered    = $this->getOption('registered');
			$registered_as = $this->getOption('registered_as');

			if (false === $registered && AuthUsers::identify($registered_as, $value, AuthUserInterface::IDENTIFIER_NAME_EMAIL)) {
				$subject->reject(new TypesInvalidValueException('OZ_FIELD_EMAIL_ALREADY_REGISTERED', $debug));

				return;
			}

			if (true === $registered && !AuthUsers::identify($registered_as, $value, AuthUserInterface::IDENTIFIER_NAME_EMAIL)) {
				$subject->reject(new TypesInvalidValueException('OZ_FIELD_EMAIL_NOT_REGISTERED', $debug));

				return;
			}
		}

		$subject->accept($value);
	}
}
