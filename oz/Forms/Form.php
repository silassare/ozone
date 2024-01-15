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

use Gobl\DBAL\Table;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\TypeDate;
use OZONE\Core\CSRF\CSRF;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Traits\FormFieldsTrait;
use OZONE\Core\Http\Request;
use OZONE\Core\Http\Uri;
use OZONE\Core\Lang\I18n;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class Form.
 */
class Form implements ArrayCapableInterface
{
	use ArrayCapableTrait;
	use FormFieldsTrait;

	/**
	 * @var \OZONE\Core\Forms\Field[]
	 */
	public array $fields = [];

	/**
	 * @var \OZONE\Core\Forms\FormStep[]
	 */
	public array $steps = [];

	/**
	 * @var \OZONE\Core\Forms\FormRule[]
	 */
	public array $rules = [];

	/**
	 * Form constructor.
	 *
	 * @param null|\OZONE\Core\Http\Uri  $submit_to
	 * @param string                     $method
	 * @param null|\OZONE\Core\CSRF\CSRF $csrf
	 */
	public function __construct(
		protected ?Uri $submit_to = null,
		protected string $method = 'POST',
		protected ?CSRF $csrf = null
	) {
		$this->method = Request::filterMethod($this->method);
	}

	/**
	 * Form destructor.
	 */
	public function __destruct()
	{
		unset($this->fields, $this->submit_to, $this->csrf);
	}

	/**
	 * Set form submit to uri.
	 *
	 * @param Uri $uri
	 *
	 * @return $this
	 */
	public function setSubmitTo(Uri $uri): static
	{
		$this->submit_to = $uri;

		return $this;
	}

	/**
	 * Gets form submit uri.
	 *
	 * @return null|\OZONE\Core\Http\Uri
	 */
	public function getSubmitTo(): ?Uri
	{
		return $this->submit_to;
	}

	/**
	 * Gets form submit method.
	 *
	 * @return string
	 */
	public function getMethod(): string
	{
		return $this->method;
	}

	/**
	 * Sets form submit method.
	 *
	 * @param string $method
	 *
	 * @return Form
	 */
	public function setMethod(string $method): self
	{
		$this->method = Request::filterMethod($method);

		return $this;
	}

	/**
	 * Adds a step to the form.
	 *
	 * A step is a group of fields that will be validated together.
	 * Use this when some fields depends on others fields.
	 *
	 * @param string                                                                          $name
	 * @param callable(\OZONE\Core\Forms\FormValidationContext):(null|\OZONE\Core\Forms\Form) $factory
	 * @param null|\OZONE\Core\Forms\FormRule                                                 $rule
	 *
	 * @return $this
	 */
	public function addStep(string $name, callable $factory, ?FormRule $rule = null): static
	{
		$if            = $rule ? static fn (FormValidationContext $fvc) => $rule->check($fvc) : null;
		$this->steps[] = new FormStep($name, $factory, $if);

		return $this;
	}

	/**
	 * Adds a field to the form.
	 *
	 * If the field is already added, it will be replaced.
	 *
	 * @param Field $field
	 *
	 * @return $this
	 */
	public function addField(Field $field): static
	{
		$name = $field->getName();

		if (isset($this->fields[$name])) {
			throw new RuntimeException(\sprintf('Field %s already exists.', $name));
		}

		$this->fields[$name] = $field;

		return $this;
	}

	/**
	 * Returns existing field or creates a new one.
	 *
	 * @param string $name
	 *
	 * @return Field
	 */
	public function field(string $name): Field
	{
		$field = $this->getField($name);
		if (!$field) {
			$field = new Field($name);

			$this->addField($field);
		}

		return $field;
	}

	/**
	 * Adds a double check fields for an existing field.
	 *
	 * @param \OZONE\Core\Forms\Field|string $field
	 *
	 * @return $this
	 */
	public function doubleCheck(Field|string $field): static
	{
		if (\is_string($field)) {
			$name  = $field;
			$field = $this->getField($field);

			if (!$field) {
				throw new RuntimeException(\sprintf('Undefined field %s.', $name));
			}
		} elseif (!$this->getField($field->getName())) {// ensure it's already added
			$this->addField($field);
		}

		$field_confirm = $this->field($field->getName() . '_confirm')
			->type($field->getType())
			->required($field->isRequired());

		$this->rule()
			->eq($field, $field_confirm, I18n::m('OZ_FIELD_SHOULD_HAVE_SAME_VALUE', [
				'field'         => $field->getName(),
				'field_confirm' => $field_confirm->getName(),
			]));

		return $this;
	}

	/**
	 * Creates a new rule and adds it to the form.
	 *
	 * @return FormRule
	 */
	public function rule(): FormRule
	{
		$rule = new FormRule();

		$this->rules[] = $rule;

		return $rule;
	}

	/**
	 * Gets a field by its name.
	 *
	 * @param string $name
	 *
	 * @return null|\OZONE\Core\Forms\Field
	 */
	public function getField(string $name): ?Field
	{
		return $this->fields[$name] ?? null;
	}

	/**
	 * Validates the form.
	 *
	 * @param FormData                        $unsafe_fd
	 * @param null|\OZONE\Core\Forms\FormData $cleaned_fd
	 * @param string                          $step_prefix
	 *
	 * @return FormData
	 *
	 * @throws InvalidFormException
	 */
	public function validate(FormData $unsafe_fd, ?FormData $cleaned_fd = null, string $step_prefix = ''): FormData
	{
		$this->assertValidCSRFToken($unsafe_fd);

		$cleaned_fd = $cleaned_fd ?? new FormData();
		$fvc        = new FormValidationContext($unsafe_fd, $cleaned_fd);

		foreach ($this->fields as $field) {
			$name = $step_prefix . $field->getName();
			if ($field->isEnabled($fvc)) {
				if ($unsafe_fd->has($name)) {
					try {
						$cleaned_fd->set($name, $field->validate($unsafe_fd->get($name), $fvc));
					} catch (TypesInvalidValueException $e) {
						/** @var InvalidFormException $e */
						$e = InvalidFormException::tryConvert($e);

						$e->suspectObject($field);

						throw $e;
					}
				} elseif ($field->isRequired()) {
					throw new InvalidFormException('OZ_FORM_MISSING_REQUIRED_FIELD', [
						'field' => $name,
						'_form' => $this,
						'_data' => $unsafe_fd->getData(),
					]);
				}
			}
		}

		foreach ($this->rules as $rule) {
			if (!$rule->check($fvc)) {
				throw new InvalidFormException($rule->getErrorMessage(), [
					// rule is prefixed by "_" because it may contain sensitive data
					'_rule' => $rule,
				]);
			}
		}

		foreach ($this->steps as $step) {
			if ($step_form = $step->build($fvc)) {
				$step_prefix .= $step->getName() . '.';

				$step_form->validate($unsafe_fd, $cleaned_fd, $step_prefix);
			}
		}

		return $cleaned_fd;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'_csrf'  => $this->csrf?->genCsrfToken(),
			'action' => (string) ($this->submit_to ?? ''),
			'method' => $this->method,
			'fields' => $this->fields,
			'steps'  => $this->steps,
		];
	}

	/**
	 * Merges a given form to this.
	 *
	 * @param Form $from
	 *
	 * @return $this
	 */
	public function merge(self $from): static
	{
		if (!$this->csrf) {
			$this->csrf = $from->csrf;
		}

		if (!$this->method) {
			$this->method = $from->method;
		}

		if (!$this->submit_to) {
			$this->submit_to = $from->submit_to;
		}

		$this->fields = \array_merge($this->fields, $from->fields);
		$this->steps  = \array_merge($this->steps, $from->steps);

		return $this;
	}

	/**
	 * Generates a form for a given table.
	 *
	 * @param \Gobl\DBAL\Table|string $table
	 *
	 * @return Form
	 */
	public static function fromTable(string|Table $table): self
	{
		if (\is_string($table)) {
			$table = db()
				->getTable($table);
		}

		$columns = $table->getColumns(false);

		$form = new self();

		foreach ($columns as $column) {
			$type = $column->getType();

			if ($type->isAutoIncremented()) {
				continue;
			}

			if ($type instanceof TypeDate && $type->isAuto()) {
				continue;
			}

			$form->field($column->getName())
				->type($type)
				->required(!$type->isNullable());
		}

		return $form;
	}

	/**
	 * Checks for the CSRF token validity.
	 *
	 * @throws InvalidFormException When the CSRF token is invalid
	 */
	private function assertValidCSRFToken(FormData $form): void
	{
		if ($this->csrf && !$this->csrf->check($form)) {
			throw new InvalidFormException(null, [
				// we use '_reason' instead of 'reason' because
				// we want only developers to know the real reason
				'_reason' => 'invalid csrf token',
			]);
		}
	}
}
