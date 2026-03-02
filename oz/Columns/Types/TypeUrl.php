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
use OZONE\Core\Http\Uri;

/**
 * Class TypeUrl.
 */
class TypeUrl extends Type
{
	public const NAME                   = 'url';
	private const ABSOLUTE_URL_PATH_REG = '~^\/[^\s?#]*(\?[^\s#]*)?(#[^\s]*)?$~u';

	/**
	 * TypeUrl constructor.
	 *
	 * @throws TypesException
	 */
	public function __construct()
	{
		parent::__construct(new TypeString(1, 2000));
	}

	/**
	 * Allow URL without scheme and host (absolute path + query + fragment).
	 *
	 * @return $this
	 */
	public function allowAbsolutePath(bool $allow_absolute_path = true): static
	{
		return $this->setOption('allow_absolute_path', $allow_absolute_path);
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

		if (!empty($value)) {
			$allow_absolute_path = (bool) $this->getOption('allow_absolute_path', false);

			if ($allow_absolute_path && \str_starts_with($value, '/')) {
				return (string) Uri::createFromString($value);
			}

			if (!\filter_var($value, \FILTER_VALIDATE_URL)) {
				throw new TypesInvalidValueException('OZ_FIELD_URL_INVALID', $debug);
			}
		}

		return $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function configure(array $options): static
	{
		if (isset($options['allow_absolute_path'])) {
			if ($options['allow_absolute_path']) {
				$this->allowAbsolutePath();
			}
		}

		return parent::configure($options);
	}
}
