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
use JsonException;
use OZONE\Core\App\Settings;
use OZONE\Core\Utils\Utils;

/**
 * Class TypeUserName.
 */
class TypeUserName extends Type
{
	public const NAME = 'user_name';

	/**
	 * TypeUserName constructor.
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function __construct()
	{
		$max = (int) Settings::get('oz.users', 'OZ_USER_NAME_MAX_LENGTH');

		parent::__construct(new TypeString(1, \max(3, $max)));
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
	 * {@inheritDoc}
	 *
	 * @throws JsonException
	 */
	public function validate($value): ?string
	{
		$debug = [
			'value' => $value,
		];

		try {
			$value = $this->base_type->validate($value);
		} catch (TypesInvalidValueException $e) {
			throw new TypesInvalidValueException('OZ_FIELD_USER_NAME_INVALID', $debug, $e);
		}

		if (!empty($value)) {
			$len   = \strlen($value);
			$value = \trim($value);

			if ($len < Settings::get('oz.users', 'OZ_USER_NAME_MIN_LENGTH')) {
				throw new TypesInvalidValueException('OZ_FIELD_USER_NAME_TOO_SHORT', $debug);
			}

			if ($len > Settings::get('oz.users', 'OZ_USER_NAME_MAX_LENGTH')) {
				throw new TypesInvalidValueException('OZ_FIELD_USER_NAME_TOO_LONG', $debug);
			}

			$value = Utils::cleanStrForDb($value);
		}

		return $value;
	}
}
