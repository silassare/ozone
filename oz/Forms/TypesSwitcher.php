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

use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\DBAL\Types\TypeString;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class TypesSwitcher.
 */
class TypesSwitcher implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	/**
	 * @var array<int, array{type: \Gobl\DBAL\Types\Interfaces\TypeInterface, rule: \OZONE\Core\Forms\FormRule}>
	 */
	private array $types = [];

	/**
	 * Adds type to the switcher.
	 *
	 * @param \OZONE\Core\Forms\FormRule                $rule
	 * @param \Gobl\DBAL\Types\Interfaces\TypeInterface $type
	 *
	 * @return $this
	 */
	public function when(FormRule $rule, TypeInterface $type): self
	{
		$this->types[] = [
			'type' => $type,
			'rule' => $rule,
		];

		return $this;
	}

	/**
	 * Gets the appropriate type.
	 *
	 * @param \OZONE\Core\Forms\FormValidationContext $fvc
	 *
	 * @return \Gobl\DBAL\Types\Interfaces\TypeInterface
	 */
	public function getType(FormValidationContext $fvc): TypeInterface
	{
		foreach ($this->types as $item) {
			$type = $item['type'];
			$rule = $item['rule'];

			if ($rule->check($fvc)) {
				return $type;
			}
		}

		return new TypeString();
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'type'  => 'types-switcher',
			'types' => $this->types,
		];
	}
}
