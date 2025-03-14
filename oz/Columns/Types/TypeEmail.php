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
use OZONE\Core\Exceptions\RuntimeException;

/**
 * Class TypeEmail.
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
	public function validate($value): ?string
	{
		$debug = [
			'email' => $value,
			'value' => $value,
		];

		try {
			$value = $this->base_type->validate($value);
		} catch (TypesInvalidValueException $e) {
			throw new TypesInvalidValueException('OZ_FIELD_EMAIL_INVALID', $debug, $e);
		}

		if (!empty($value)) {
			if (!\filter_var($value, \FILTER_VALIDATE_EMAIL)) {
				throw new TypesInvalidValueException('OZ_FIELD_EMAIL_INVALID', $debug);
			}

			$registered    = $this->getOption('registered');
			$registered_as = $this->getOption('registered_as');

			if (false === $registered && AuthUsers::identify($registered_as, $value, AuthUserInterface::IDENTIFIER_NAME_EMAIL)) {
				throw new TypesInvalidValueException('OZ_FIELD_EMAIL_ALREADY_REGISTERED', $debug);
			}

			if (true === $registered && !AuthUsers::identify($registered_as, $value, AuthUserInterface::IDENTIFIER_NAME_EMAIL)) {
				throw new TypesInvalidValueException('OZ_FIELD_EMAIL_NOT_REGISTERED', $debug);
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
			if (!isset($options['registered_as'])) {
				throw new RuntimeException('Missing \'registered_as\' property.');
			}

			if ($options['registered']) {
				$this->registered($options['registered_as']);
			} else {
				$this->notRegistered($options['registered_as']);
			}
		}

		return parent::configure($options);
	}
}
