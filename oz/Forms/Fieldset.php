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
use OZONE\Core\Exceptions\RuntimeException;
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
 * Fieldsets do NOT support nested fieldsets, `expect()` pre-validation rules,
 * or the `resumable()` mechanism — those belong to the parent {@see Form}.
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
	 * @var null|Closure(FormData):Fieldset
	 */
	private ?Closure $t_dynamic_factory = null;

	/**
	 * Fieldset constructor.
	 *
	 * @param Form         $form the parent form
	 * @param string       $name the fieldset name (validated as a dot-path segment)
	 * @param null|RuleSet $if   optional condition controlling whether this fieldset is active
	 */
	private function __construct(
		Form $form,
		string $name,
		?RuleSet $if = null,
	) {
		$this->name($name);
		$this->t_parent_form = $form;
		$this->t_if          = $if;
	}

	/**
	 * Fieldset destructor.
	 */
	#[Override]
	public function __destruct()
	{
		parent::__destruct();
		unset(
			$this->t_if,
			$this->t_parent_form,
			$this->t_dynamic_factory,
		);
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
	 * @param null|RuleSet        $if          optional condition for this fieldset
	 *
	 * @return static
	 */
	public static function static(Form $parent_form, string $name, callable $callback, ?RuleSet $if = null): static
	{
		$fs = new self($parent_form, $name, $if);

		Closure::fromCallable($callback)($fs);

		return $fs;
	}

	/**
	 * Creates a dynamic fieldset whose structure is built at validation time from accumulated cleaned data.
	 *
	 * The factory is called during {@see Form::validate()} with the cleaned `FormData` collected
	 * from all preceding fields and fieldsets. It must return a `Fieldset` instance (created via
	 * {@see self::static()}). Return a fieldset with no fields to emit an empty section.
	 *
	 * ```php
	 * $form->dynamicFieldset('extras', function (FormData $fd): Fieldset {
	 *     $fs = Fieldset::static($form, 'extras', function (Fieldset $f) use ($fd): void {
	 *         if ('pro' === $fd->get('plan')) {
	 *             $f->string('promo_code');
	 *         }
	 *     });
	 *     return $fs;
	 * });
	 * ```
	 *
	 * @param Form                        $parent_form the parent form
	 * @param string                      $name        the fieldset name
	 * @param callable(FormData):Fieldset $factory     builds the fieldset from cleaned data
	 * @param null|RuleSet                $if          optional condition for this fieldset
	 *
	 * @return static
	 */
	public static function dynamic(Form $parent_form, string $name, callable $factory, ?RuleSet $if = null): static
	{
		$fs                    = new self($parent_form, $name, $if);
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
	 * Returns (or lazily initialises) the condition that controls whether this
	 * fieldset participates in validation. Evaluated against the parent form's
	 * accumulated cleaned data.
	 *
	 * @return RuleSet
	 */
	public function if(): RuleSet
	{
		if (!isset($this->t_if)) {
			$this->t_if = new RuleSet();
		}

		return $this->t_if;
	}

	/**
	 * Returns true when this fieldset's condition is satisfied (or when no condition is set).
	 *
	 * @param FormData $fd cleaned form data accumulated so far
	 */
	public function isEnabled(FormData $fd): bool
	{
		if (null === $this->t_if) {
			return true;
		}

		return $this->t_if->check($fd);
	}

	/**
	 * Builds the active fieldset for the given accumulated cleaned data.
	 *
	 * Returns:
	 *  - `null` when the condition is not satisfied (fieldset is skipped).
	 *  - `$this` for static fieldsets when enabled.
	 *  - A new `Fieldset` instance from the dynamic factory when enabled.
	 *
	 * @param FormData $fd accumulated cleaned data from preceding fields
	 *
	 * @return null|Fieldset
	 */
	public function build(FormData $fd): ?self
	{
		if (!$this->isEnabled($fd)) {
			return null;
		}

		if (!$this->t_is_dynamic) {
			return $this;
		}

		return $this->callDynamicFactory($fd);
	}

	/**
	 * Validates this fieldset against the given form data.
	 *
	 * @param FormData $unsafe_fd  Raw (unsafe) form data from the request
	 * @param FormData $cleaned_fd Pre-filled validated data (e.g. from a resume cache); merged with newly validated fields
	 */
	public function validate(FormData $unsafe_fd, FormData $cleaned_fd): void
	{
		$this->checkPreValidationRules($unsafe_fd);

		$this->checkFields($unsafe_fd, $cleaned_fd);

		$this->checkPostValidationRules($cleaned_fd);
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
	 *  if: null|RuleSet
	 * }
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'ref'    => $this->t_parent_form->getRef($this->getName()),
			'name'   => $this->getName(),
			'legend' => $this->t_legend,
			'type'   => $this->t_is_dynamic ? 'dynamic' : 'static',
			'fields' => $this->t_is_dynamic ? null : $this->getFields(),
			'if'     => $this->t_if,
		];
	}

	/**
	 * Calls the dynamic factory and validates its return type.
	 *
	 * @param FormData $fd
	 *
	 * @return Fieldset
	 */
	private function callDynamicFactory(FormData $fd): self
	{
		$result = ($this->t_dynamic_factory)($fd);

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
