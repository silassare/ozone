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
use Override;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Enums\RuleSetCondition;
use OZONE\Core\Forms\Enums\RuleSetDataType;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Str;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class TypesSwitcher.
 *
 * Picks a field's type at validation time from the data cleaned so far, so a
 * single field can accept different shapes depending on earlier answers.
 *
 * Conditions are evaluated against cleaned data ({@see RuleSetDataType::CLEANED}),
 * which means they compare against validated values, not raw request strings.
 *
 * @see self::when()
 * @see self::otherwise()
 */
class TypesSwitcher implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	/**
	 * @var array<int, array{type: TypeInterface, rule: RuleSet}>
	 */
	private array $types = [];

	private ?TypeInterface $default_type = null;

	/**
	 * Reference of the field this switcher is attached to, used to build stable
	 * references for the condition rule sets. Set by {@see Field::type()}.
	 */
	private string $t_owner_ref = '';

	/**
	 * Deep-copies the branch types and their conditions.
	 */
	public function __clone()
	{
		foreach ($this->types as $i => $item) {
			$this->types[$i] = [
				'type' => clone $item['type'],
				'rule' => clone $item['rule'],
			];
		}

		if (null !== $this->default_type) {
			$this->default_type = clone $this->default_type;
		}
	}

	/**
	 * Adds a type to the switcher, guarded by a condition.
	 *
	 * The condition is built inside $rules against the data cleaned so far, so
	 * it compares against validated values rather than raw request strings:
	 *
	 * ```php
	 * $form->switcher('doc_number')->configureType(static fn (TypesSwitcher $s) => $s
	 *     ->when(static fn (RuleSet $rs) => $rs->eq('doc_type', 'passport'), new TypeString(9, 9))
	 *     ->when(static fn (RuleSet $rs) => $rs->eq('doc_type', 'id_card'), new TypeString(12, 12)));
	 * ```
	 *
	 * Because the cleaned store is filled in field declaration order, a condition
	 * can only read fields declared before the switcher's own field.
	 *
	 * @param callable(RuleSet):void $rules populates the condition in-place
	 * @param TypeInterface          $type  the type to use when the condition passes
	 *
	 * @return $this
	 */
	public function when(callable $rules, TypeInterface $type): static
	{
		$rule = RuleSet::create(
			RuleSetCondition::AND,
			RuleSetDataType::CLEANED,
			'' === $this->t_owner_ref ? '' : \sprintf('%s@switch[%d]', $this->t_owner_ref, \count($this->types))
		);

		$rules($rule);

		$this->types[] = [
			'type' => $type,
			'rule' => $rule,
		];

		return $this;
	}

	/**
	 * Gets the appropriate type.
	 *
	 * @param FormValidationContext $ctx
	 *
	 * @return TypeInterface
	 *
	 * @throws RuntimeException when no condition matches and no default type is set
	 */
	public function getType(FormValidationContext $ctx): TypeInterface
	{
		foreach ($this->types as $item) {
			$type = $item['type'];
			$rule = $item['rule'];

			if ($rule->check($ctx)) {
				return $type;
			}
		}

		if (null !== $this->default_type) {
			return $this->default_type;
		}

		throw new RuntimeException(\sprintf(
			'No type matched in "%s" and no default type was set. Add a fallback with "%s".',
			self::class,
			Str::callableName([$this, 'otherwise'])
		));
	}

	/**
	 * Binds this switcher to the field that owns it.
	 *
	 * Called by {@see Field::type()} so condition rule sets can derive a stable
	 * reference from the owning field.
	 *
	 * @internal
	 */
	public function bindTo(Field $field): void
	{
		$this->t_owner_ref = $field->getRef();
	}

	/**
	 * Sets the type used when no condition matches.
	 *
	 * Without a default, {@see self::getType()} throws instead of silently
	 * falling back to a permissive type.
	 *
	 * @param TypeInterface $type
	 *
	 * @return $this
	 */
	public function otherwise(TypeInterface $type): static
	{
		$this->default_type = $type;

		return $this;
	}

	/**
	 * The condition of each branch, in declaration order.
	 *
	 * @return list<RuleSet>
	 */
	public function getConditions(): array
	{
		return \array_values(\array_map(static fn (array $item) => $item['rule'], $this->types));
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array{
	 *  type: 'types-switcher',
	 *  types: list<array{type: array<string, mixed>, rule: RuleSet}>,
	 *  otherwise: null|array<string, mixed>
	 * }
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'type'  => 'types-switcher',
			'types' => \array_map(static fn ($item) => [
				'type' => Field::frontendType($item['type']),
				'rule' => $item['rule'],
			], $this->types),
			'otherwise' => null === $this->default_type ? null : Field::frontendType($this->default_type),
		];
	}
}
