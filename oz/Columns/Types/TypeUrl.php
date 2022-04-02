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

/**
 * Class TypeUrl.
 */
class TypeUrl extends Type
{
	public const NAME = 'url';

	/**
	 * TypeUrl constructor.
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function __construct()
	{
		parent::__construct(new TypeString(1, 2000));
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
	public function setDefault($default): self
	{
		$this->base_type->setDefault($default);

		return parent::setDefault($default);
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate($value): string
	{
		$debug = [
			'value' => $value,
		];

		try {
			$value = $this->base_type->validate($value);
		} catch (TypesInvalidValueException $e) {
			throw new TypesInvalidValueException('OZ_FIELD_URL_INVALID', $debug, $e);
		}

		if (!empty($value) && !\filter_var($value, \FILTER_VALIDATE_URL)) {
			throw new TypesInvalidValueException('OZ_FIELD_URL_INVALID', $debug);
		}

		return $value;
	}
}
