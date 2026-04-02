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
use OZONE\Core\Exceptions\RuntimeException;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Str;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class FormStep.
 */
final class FormStep implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	private string $t_name;

	/**
	 * @var null|callable():Form|Form
	 */
	private $t_static_form;

	/**
	 * @var null|callable(FormData):Form
	 */
	private $t_dynamic_form_factory;

	private ?RuleSet $t_if = null;
	private Form $t_parent_form;

	/**
	 * FormStep constructor.
	 *
	 * @param Form         $form The parent form
	 * @param string       $name The step name
	 * @param null|RuleSet $if   The step condition
	 */
	private function __construct(
		Form $form,
		string $name,
		?RuleSet $if = null,
	) {
		FormUtils::assertValidFieldName($name);

		$this->t_name           = $name;
		$this->t_parent_form    = $form;
		$this->t_if             = $if;
	}

	/**
	 * FormStep destructor.
	 */
	public function __destruct()
	{
		unset($this->t_if, $this->t_parent_form, $this->t_static_form, $this->t_dynamic_form_factory);
	}

	/**
	 * Gets the step reference.
	 */
	public function getRef(): string
	{
		return $this->t_parent_form->getRef($this->t_name);
	}

	/**
	 * Set the condition for this step to be enabled.
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
	 * Creates a new static form step.
	 *
	 * @param string               $name The step name
	 * @param callable():Form|Form $form The step form or factory
	 * @param null|RuleSet         $if   The step condition
	 *
	 * @return static
	 */
	public static function static(Form $parent_form, string $name, callable|Form $form, ?RuleSet $if = null): static
	{
		$s = new self($parent_form, $name, $if);

		$s->t_static_form = $form;

		return $s;
	}

	/**
	 * Creates a new dynamic form step.
	 *
	 * @param Form                    $parent_form The parent form
	 * @param string                  $name        The step name
	 * @param callable(FormData):Form $factory     The step form factory
	 * @param null|RuleSet            $if          The step condition
	 *
	 * @return static
	 */
	public static function dynamic(Form $parent_form, string $name, callable $factory, ?RuleSet $if = null): static
	{
		$s = new self($parent_form, $name, $if);

		$s->t_dynamic_form_factory = $factory;

		return $s;
	}

	/**
	 * Gets the step name.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->t_name;
	}

	/**
	 * Checks if this step is static.
	 */
	public function isStatic(): bool
	{
		return null !== $this->t_static_form;
	}

	/**
	 * Checks if this step is dynamic (factory).
	 */
	public function isDynamic(): bool
	{
		return null !== $this->t_dynamic_form_factory;
	}

	/**
	 * Gets the step form, only for static steps.
	 *
	 * @throws RuntimeException if this step form is dynamic
	 */
	public function getStaticForm(): Form
	{
		if (null !== $this->t_static_form) {
			if ($this->t_static_form instanceof Form) {
				return $this->t_static_form;
			}

			$form = $this->callFactory($this->t_static_form);

			if (null === $form) {
				throw (new RuntimeException(
					\sprintf(
						'Static step form factory should return instance of "%s" not null.',
						Form::class,
					)
				))->suspectCallable($this->t_static_form);
			}

			return $form;
		}

		throw new RuntimeException(\sprintf('This step form is dynamic, and can only be accessed via "%s".', Str::callableName([$this, 'build'])));
	}

	/**
	 * Check if the step is enabled.
	 *
	 * @param FormData $fd
	 *
	 * @return bool
	 */
	public function isEnabled(FormData $fd): bool
	{
		if (null === $this->t_if) {
			return true;
		}

		return $this->t_if->check($fd);
	}

	/**
	 * Builds this step form.
	 *
	 * @param FormData $fd
	 *
	 * @return null|Form
	 */
	public function build(FormData $fd): ?Form
	{
		if (!$this->isEnabled($fd)) {
			return null;
		}

		if ($this->isStatic()) {
			return $this->getStaticForm();
		}

		return $this->callFactory($this->t_dynamic_form_factory, $fd);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array{
	 *  ref: string,
	 *  name: string,
	 *  type: 'dynamic'|'static',
	 *  form: null|Form,
	 *  if: null|RuleSet
	 * }
	 */
	#[Override]
	public function toArray(): array
	{
		$static_form = null !== $this->t_static_form ? $this->getStaticForm() : null;

		return [
			'ref'  => $this->getRef(),
			'name' => $this->t_name,
			'type' => $this->isStatic() ? 'static' : 'dynamic',
			'form' => $static_form,
			'if'   => $this->t_if,
		];
	}

	/**
	 * Calls a form factory and returns the built form.
	 *
	 * @param (callable(): Form)|callable(FormData): ?Form $factory The factory to call
	 * @param null|FormData                                $fd      passed for dynamic steps; null for static step factories
	 */
	private function callFactory(callable $factory, ?FormData $fd = null): ?Form
	{
		$form =  \call_user_func($factory, $fd);

		if (null !== $form && !($form instanceof Form)) {
			throw (new RuntimeException(
				\sprintf(
					'Step form factory should return instance of "%s" or "null" not: %s',
					Form::class,
					\get_debug_type($form)
				)
			))->suspectCallable($factory);
		}

		return $form;
	}
}
