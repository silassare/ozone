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
use OZONE\OZ\Users\UsersManager;

/**
 * Class TypeEmail.
 */
class TypeEmail extends Type
{
	public const NAME = 'email';

	/**
	 * TypeEmail constructor.
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
	public function default($default): self
	{
		$this->base_type->default($default);

		return parent::default($default);
	}

	/**
	 * To accept email that are registered only.
	 *
	 * @return $this
	 */
	public function registered(): self
	{
		return $this->setOption('registered', true);
	}

	/**
	 * To accept email that are not registered only.
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

			$registered = $this->getOption('registered');

			if (false === $registered && UsersManager::searchUserWithEmail($value)) {
				throw new TypesInvalidValueException('OZ_FIELD_EMAIL_ALREADY_REGISTERED', $debug);
			}

			if (true === $registered && !UsersManager::searchUserWithEmail($value)) {
				throw new TypesInvalidValueException('OZ_FIELD_EMAIL_NOT_REGISTERED', $debug);
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
