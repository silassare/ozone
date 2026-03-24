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
use Override;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class TypesSwitcher.
 */
class TypesSwitcher implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	/**
	 * @var array<int, array{type: TypeInterface, rule: RuleSet}>
	 */
	private array $types = [];

	/**
	 * Adds type to the switcher.
	 *
	 * @param RuleSet       $rule
	 * @param TypeInterface $type
	 *
	 * @return $this
	 */
	public function when(RuleSet $rule, TypeInterface $type): self
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
	 * @param FormData $fd
	 *
	 * @return TypeInterface
	 */
	public function getType(FormData $fd): TypeInterface
	{
		foreach ($this->types as $item) {
			$type = $item['type'];
			$rule = $item['rule'];

			if ($rule->check($fd)) {
				return $type;
			}
		}

		return new TypeString();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array{
	 *  type: 'types-switcher',
	 *  types: list<array{type: TypeInterface, rule: RuleSet}>
	 * }
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'type'  => 'types-switcher',
			'types' => \array_map(static fn ($item) => [
				'type' => Field::cleanType($item['type']),
				'rule' => $item['rule'],
			], $this->types),
		];
	}
}
