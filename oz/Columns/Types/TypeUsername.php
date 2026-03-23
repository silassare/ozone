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
use OZONE\Core\Users\UsernameUtils;

/**
 * Class TypeUsername.
 *
 * @extends Type<mixed, null|string>
 */
class TypeUsername extends Type
{
	public const NAME = 'username';

	/**
	 * TypeUsername constructor.
	 *
	 * @throws TypesException
	 */
	public function __construct()
	{
		$max = (int) Settings::get('oz.users', 'OZ_USER_NAME_MAX_LENGTH');

		parent::__construct(new TypeString(1, \max(3, $max) * 2));
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
	 * To accept only registered usernames.
	 *
	 * @return $this
	 */
	public function registered(): static
	{
		return $this->setOption('registered', true);
	}

	/**
	 * To accept only usernames that are not registered.
	 *
	 * @return $this
	 */
	public function notRegistered(): static
	{
		return $this->setOption('registered', false);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function configure(array $options): static
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
			$subject->reject(new TypesInvalidValueException('OZ_FIELD_USER_NAME_INVALID', null, $e));

			return;
		}

		$debug = [
			'value' => $value,
		];

		if (!empty($value)) {
			$value = \trim($value);
			$len   = \strlen($value);

			/** @var null|bool $registered */
			$registered = $this->getOption('registered');

			if ($len < Settings::get('oz.users', 'OZ_USER_NAME_MIN_LENGTH')) {
				$subject->reject(new TypesInvalidValueException('OZ_FIELD_USER_NAME_TOO_SHORT', $debug));

				return;
			}

			if ($len > Settings::get('oz.users', 'OZ_USER_NAME_MAX_LENGTH')) {
				$subject->reject(new TypesInvalidValueException('OZ_FIELD_USER_NAME_TOO_LONG', $debug));

				return;
			}

			if (!\preg_match(Settings::get('oz.users', 'OZ_USER_NAME_PATTERN'), $value)) {
				$subject->reject(new TypesInvalidValueException('OZ_FIELD_USER_NAME_INVALID_CHARACTERS', $debug));

				return;
			}

			if (false === $registered && UsernameUtils::exists($value)) {
				$subject->reject(new TypesInvalidValueException('OZ_FIELD_USER_NAME_ALREADY_REGISTERED', $debug));

				return;
			}

			if (true === $registered && !UsernameUtils::exists($value)) {
				$subject->reject(new TypesInvalidValueException('OZ_FIELD_USER_NAME_NOT_REGISTERED', $debug));

				return;
			}
		}

		$subject->accept($value);
	}
}
