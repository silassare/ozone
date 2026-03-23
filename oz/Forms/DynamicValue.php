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

namespace OZONE\Core\Forms;

use Override;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class DynamicValue.
 *
 * @template T The type of the dynamic value.
 */
final class DynamicValue implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	/**
	 * @var callable(FormData):T
	 */
	private $factory;

	/**
	 * DynamicValue constructor.
	 *
	 * @param callable(FormData):T $factory
	 */
	public function __construct(callable $factory)
	{
		$this->factory = $factory;
	}

	public function __destruct()
	{
		unset($this->factory);
	}

	/**
	 * Gets the dynamic value.
	 *
	 * @return T
	 */
	public function getValue(FormData $fd): mixed
	{
		return \call_user_func($this->factory, $fd);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array{
	 *  $dynamic: true
	 * }
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'$dynamic' => true,
		];
	}
}
