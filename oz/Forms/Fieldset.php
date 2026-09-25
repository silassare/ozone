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

use Closure;
use Override;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Enums\RuleSetCondition;
use OZONE\Core\Forms\Enums\RuleSetDataType;
use OZONE\Core\Forms\Traits\FieldContainerHelpersTrait;
use OZONE\Core\Lang\I18n;
use OZONE\Core\Lang\I18nMessage;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class Fieldset.
 *
 * A named group of fields within a {@see Form}. Fieldsets let the client
 * render related inputs together (e.g. an address block, a billing section).
 *
 * Two variants:
 *  - **Static**: callback `callable(Fieldset):void` is executed immediately at
 *    definition time and populates the fieldset's fields in-place.
 *  - **Dynamic**: factory `callable(FormData):Fieldset` is called at validation
 *    time with the accumulated cleaned data, allowing structure to adapt to
 *    previously validated input.
 *
 * Fieldsets do NOT support nested fieldsets, or the `resumable()` mechanism — those belong to the parent {@see Form}.
 *
 * All fields inside a fieldset have their refs prefixed with
 * `{fieldset_name}.`, so clients submit flat dotted keys
 * (e.g. `address.street`) to match.
 */
final class Fieldset extends AbstractFieldContainer implements ArrayCapableInterface
{
	use ArrayCapableTrait;
	use FieldContainerHelpersTrait;

	private ?I18nMessage $t_legend = null;

	private ?RuleSet $t_if = null;

	private Form $t_parent_form;

	private bool $t_is_dynamic = false;

	/**
	 * @var null|Closure(FormValidationContext):Fieldset
	 */
	private ?Closure $t_dynamic_factory = null;

	/**
	 * Fieldset constructor.
	 *
	 * Conditions are not passed in: use {@see self::if()} on the returned
	 * instance, which builds a correctly-typed rule set that is scoped to this
	 * fieldset rather than registered as a form-level assertion.
	 *
	 * @param Form   $form the parent form
	 * @param string $name the fieldset name (validated as a dot-path segment)
	 */
	private function __construct(
		Form $form,
		string $name,
	) {
		$this->name($name);
		$this->t_parent_form = $form;
	}

	/**
	 * Deep-copies the fieldset's own fields and condition.
	 *
	 * The parent form reference is deliberately kept: a merged fieldset keeps its
	 * original namespace so its refs stay identical across requests and across
	 * merges (see {@see Form::merge()}).
	 */
	public function __clone()
	{
		if (null !== $this->t_if) {
			$this->t_if = clone $this->t_if;
		}

		$this->cloneOwnFields();
	}

	/**
	 * {@inheritDoc}
	 *
	 * Returns the fully-qualified reference for a child field by prefixing
	 * it with `{fieldset_name}.` and then delegating to the parent form.
	 *
	 * Example: fieldset `address` in unnamed form + field `street` -> `address.street`
	 */
	#[Override]
	public function getRef(string $name): string
	{
		return $this->t_parent_form->getRef($this->getName() . '.' . $name);
	}

	/**
	 * Returns this fieldset's own reference, as opposed to {@see self::getRef()}
	 * which builds the reference of a child field.
	 */
	public function getSelfRef(): string
	{
		return $this->t_parent_form->getRef($this->getName());
	}

	/**
	 * Returns this fieldset's name.
	 */
	#[Override]
	public function getName(): string
	{
		return parent::getName() ?? throw new RuntimeException('Fieldset has no name.');
	}

	/**
	 * Creates a static fieldset whose fields are defined immediately via a mutate-in-place callback.
	 *
	 * The callback receives the new `Fieldset` instance and is called once at definition time:
	 *
	 * ```php
	 * $form->fieldset('address', function (Fieldset $fs): void {
	 *     $fs->string('street')->required();
	 *     $fs->string('city')->required();
	 * });
	 * ```
	 *
	 * @param Form                $parent_form the parent form
	 * @param string              $name        the fieldset name
	 * @param callable(self):void $callback    populates the fieldset in-place
	 *
	 * @return static
	 */
	public static function static(Form $parent_form, string $name, callable $callback): static
	{
		$fs = new self($parent_form, $name);

		Closure::fromCallable($callback)($fs);

		return $fs;
	}

	/**
	 * Creates a dynamic fieldset whose structure is built at validation time from accumulated cleaned data.
	 *
	 * The factory is called during {@see Form::validate()} with the validation context,
	 * whose cleaned store holds everything collected from preceding fields and fieldsets.
	 * It must return a `Fieldset` instance (created via {@see self::static()}). Return a
	 * fieldset with no fields to emit an empty section.
	 *
	 * ```php
	 * $form->dynamicFieldset('extras', function (FormValidationContext $ctx) use ($form): Fieldset {
	 *     return Fieldset::static($form, 'extras', function (Fieldset $f) use ($ctx): void {
	 *         if ('pro' === $ctx->getCleanFormData()->get('plan')) {
	 *             $f->string('promo_code');
	 *         }
	 *     });
	 * });
	 * ```
	 *
	 * @param Form                                     $parent_form the parent form
	 * @param string                                   $name        the fieldset name
	 * @param callable(FormValidationContext):Fieldset $factory     builds the fieldset from the context
	 *
	 * @return static
	 */
	public static function dynamic(Form $parent_form, string $name, callable $factory): static
	{
		$fs                    = new self($parent_form, $name);
		$fs->t_is_dynamic      = true;
		$fs->t_dynamic_factory = Closure::fromCallable($factory);

		return $fs;
	}

	/**
	 * Gets the fieldset legend (display label), or null if unset.
	 */
	public function getLegend(): ?I18nMessage
	{
		return $this->t_legend;
	}

	/**
	 * Sets the fieldset legend (the visible group label shown to the user).
	 *
	 * @param I18nMessage|string $legend
	 *
	 * @return $this
	 */
	public function legend(I18nMessage|string $legend): static
	{
		$this->t_legend = $legend instanceof I18nMessage ? $legend : I18n::m($legend);

		return $this;
	}

	/**
	 * Whether this fieldset is static (fields known at definition time).
	 */
	public function isStatic(): bool
	{
		return !$this->t_is_dynamic;
	}

	/**
	 * Whether this fieldset is dynamic (fields built from FormData at validation time).
	 */
	public function isDynamic(): bool
	{
		return $this->t_is_dynamic;
	}

	/**
	 * Returns the condition that controls whether this fieldset participates in
	 * validation, or null when no condition has been set.
	 *
	 * Use {@see self::if()} to set or read the condition via the fluent builder.
	 *
	 * @return null|RuleSet
	 */
	public function getIf(): ?RuleSet
	{
		return $this->t_if ?? null;
	}

	/**
	 * Returns (or lazily initialises) the condition that controls whether this
	 * fieldset participates in validation. Evaluated against the parent form's
	 * accumulated cleaned data.
	 *
	 * @return RuleSet
	 */
	public function if(): RuleSet
	{
		if (!isset($this->t_if)) {
			$this->t_if = RuleSet::create(
				RuleSetCondition::AND,
				RuleSetDataType::CLEANED,
				$this->getSelfRef() . '@if'
			);
		}

		return $this->t_if;
	}

	/**
	 * Returns true when this fieldset's condition is satisfied (or when no condition is set).
	 *
	 * @param FormValidationContext $ctx
	 */
	public function isEnabled(FormValidationContext $ctx): bool
	{
		if (null === $this->t_if) {
			return true;
		}

		return $this->t_if->check($ctx);
	}

	/**
	 * Builds the active fieldset for the given accumulated cleaned data.
	 *
	 * Returns:
	 *  - `null` when the condition is not satisfied (fieldset is skipped).
	 *  - `$this` for static fieldsets when enabled.
	 *  - A new `Fieldset` instance from the dynamic factory when enabled.
	 *
	 * @param FormValidationContext $ctx
	 *
	 * @return null|Fieldset
	 */
	public function build(FormValidationContext $ctx): ?self
	{
		if (!$this->isEnabled($ctx)) {
			return null;
		}

		if (!$this->t_is_dynamic) {
			return $this;
		}

		return $this->callDynamicFactory($ctx);
	}

	/**
	 * Validates this fieldset against the given form data.
	 *
	 * @param FormValidationContext $ctx
	 *
	 * @throws InvalidFormException
	 */
	public function validate(FormValidationContext $ctx): void
	{
		$this->checkPreValidationRules($ctx);

		$this->checkFields($ctx);

		$this->checkPostValidationRules($ctx);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array{
	 *  ref: string,
	 *  name: string,
	 *  legend: null|I18nMessage,
	 *  type: 'dynamic'|'static',
	 *  fields: null|array<string, Field>,
	 *  expect: null|list<RuleSet>,
	 *  ensure: null|list<RuleSet>,
	 *  if: null|RuleSet
	 * }
	 */
	#[Override]
	public function toArray(): array
	{
		// A dynamic fieldset's fields and rules are those of the fieldset its factory builds, unknown
		// until then.
		return [
			'ref'    => $this->getSelfRef(),
			'name'   => $this->getName(),
			'legend' => $this->t_legend,
			'type'   => $this->t_is_dynamic ? 'dynamic' : 'static',
			'fields' => $this->t_is_dynamic ? null : $this->getFields(),
			// Checked before its fields and after them (validate()).
			'expect' => $this->t_is_dynamic ? null : $this->getPreValidationRules(),
			'ensure' => $this->t_is_dynamic ? null : $this->getPostValidationRules(),
			'if'     => $this->t_if,
		];
	}

	/**
	 * Calls the dynamic factory and validates its return type.
	 *
	 * @param FormValidationContext $ctx
	 *
	 * @return Fieldset
	 */
	private function callDynamicFactory(FormValidationContext $ctx): self
	{
		$result = ($this->t_dynamic_factory)($ctx);

		if (!$result instanceof self) {
			throw (new RuntimeException(
				\sprintf(
					'Dynamic fieldset factory must return an instance of "%s", got: %s.',
					self::class,
					\get_debug_type($result)
				)
			))->suspectCallable($this->t_dynamic_factory);
		}

		return $result;
	}
}
