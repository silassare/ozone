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
use OZONE\Core\App\Settings;

/**
 * Class TypePassword.
 */
class TypePassword extends Type
{
	public const NAME = 'password';

	/**
	 * TypePassword constructor.
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
	 * Sets password min length.
	 *
	 * @param int $min
	 *
	 * @return $this
	 *
	 * @throws TypesException
	 */
	public function min(int $min): static
	{
		self::assertSafeIntRange($min, $this->getOption('max', 255), 0);

		return $this->setOption('min', $min);
	}

	/**
	 * Sets password max length.
	 *
	 * @param int $max
	 *
	 * @return $this
	 *
	 * @throws TypesException
	 */
	public function max(int $max): static
	{
		self::assertSafeIntRange($this->getOption('min', 0), $max, 0);

		return $this->setOption('max', $max);
	}

	/**
	 * Enable password security check.
	 *
	 * @param bool $secure
	 *
	 * @return $this
	 */
	public function secure(bool $secure = true): static
	{
		return $this->setOption('secure', $secure);
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
	 * @throws TypesException
	 */
	public function configure(array $options): static
	{
		if (isset($options['min'])) {
			$this->min((int) $options['min']);
		}

		if (isset($options['max'])) {
			$this->max((int) $options['max']);
		}

		if (isset($options['secure'])) {
			$this->secure((bool) $options['secure']);
		}

		return parent::configure($options);
	}

	/**
	 * {@inheritDoc}
	 */
	public function validate($value): ?string
	{
		$def_min = Settings::get('oz.users', 'OZ_USER_PASS_MIN_LENGTH');
		$def_max = Settings::get('oz.users', 'OZ_USER_PASS_MAX_LENGTH');
		$min     = $this->getOption('min', $def_min);
		$max     = $this->getOption('max', $def_max);

		$debug = [
			'value' => $value,
			'min'   => $min,
			'max'   => $max,
		];

		try {
			$value = $this->base_type->validate($value);
		} catch (TypesInvalidValueException $e) {
			throw new TypesInvalidValueException('OZ_FIELD_PASS_INVALID', $debug, $e);
		}

		if (!empty($value)) {
			$value = (string) $value;
			$len   = \strlen($value);

			if ($this->getOption('secure')) {
				$has_upper = false;
				$has_lower = false;
				$has_digit = false;
				$has_other = false;

				for ($i = 0; $i < $len; ++$i) {
					$char = $value[$i];

					if (\ctype_upper($char)) {
						$has_upper = true;
					} elseif (\ctype_lower($char)) {
						$has_lower = true;
					} elseif (\ctype_digit($char)) {
						$has_digit = true;
					} else {
						$has_other = true;
					}
				}

				if (!$has_upper || !$has_lower || !$has_digit || !$has_other) {
					throw new TypesInvalidValueException('OZ_FIELD_PASS_NOT_SECURE', [
						'has_upper' => $has_upper,
						'has_lower' => $has_lower,
						'has_digit' => $has_digit,
						'has_other' => $has_other,
					] + $debug);
				}
			}

			if ($len < $min) {
				throw new TypesInvalidValueException('OZ_FIELD_PASS_TOO_SHORT', $debug);
			}

			if ($len > $max) {
				throw new TypesInvalidValueException('OZ_FIELD_PASS_TOO_LONG', $debug);
			}
		}

		return $value;
	}
}
