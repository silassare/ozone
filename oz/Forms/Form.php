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

namespace OZONE\OZ\Forms;

use Gobl\DBAL\Table;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\TypeDate;
use OZONE\OZ\Core\DbManager;
use OZONE\OZ\CSRF\CSRF;
use OZONE\OZ\Exceptions\InvalidFormException;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\Http\Request;
use OZONE\OZ\Http\Uri;
use OZONE\OZ\Lang\I18n;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class Form.
 */
class Form implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	/**
	 * @var \OZONE\OZ\Forms\Field[]
	 */
	public array $fields = [];

	/**
	 * @var \OZONE\OZ\Forms\FormStep[]
	 */
	public array $steps = [];

	/**
	 * @var \OZONE\OZ\Forms\FormRule[]
	 */
	public array $rules = [];

	/**
	 * Form constructor.
	 *
	 * @param null|\OZONE\OZ\Http\Uri  $submit_to
	 * @param string                   $method
	 * @param null|\OZONE\OZ\CSRF\CSRF $csrf
	 */
	public function __construct(protected ?Uri $submit_to = null, protected string $method = 'POST', protected ?CSRF $csrf = null)
	{
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
	 * @param \OZONE\OZ\Http\Uri $uri
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
	 * @return null|\OZONE\OZ\Http\Uri
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
	 * @return \OZONE\OZ\Forms\Form
	 */
	public function setMethod(string $method): self
	{
		$this->method = Request::filterMethod($method);

		return $this;
	}

	public function addStep(string $name, callable $builder, ?FormRule $rule = null): static
	{
		$this->steps[] = new FormStep($name, $builder, $rule ? [$rule, 'check'] : null);

		return $this;
	}

	public function addField(Field $field): static
	{
		$this->fields[$field->getName()] = $field;

		return $this;
	}

	public function field(string $name): Field
	{
		$field = new Field($name);

		$this->addField($field);

		return $field;
	}

	/**
	 * Adds a double check fields for an existing field.
	 *
	 * @param \OZONE\OZ\Forms\Field|string $field
	 *
	 * @return $this
	 */
	public function doubleCheck(string|Field $field): static
	{
		if (\is_string($field)) {
			$name  = $field;
			$field = $this->getField($field);

			if (!$field) {
				throw new RuntimeException(\sprintf('Undefined field %s.', $name));
			}
		} else {
			// ensure it's already added
			$this->addField($field);
		}

		$field_verify = $this->field($field->getName() . '_verify')
			->type($field->getType())
			->required($field->isRequired());

		$this->rule()
			->eq($field, $field_verify, I18n::m('OZ_FIELDS_SHOULD_HAVE_SAME_VALUE', [
				'field'        => $field->getName(),
				'field_verify' => $field_verify->getName(),
			]));

		return $this;
	}

	public function rule(): FormRule
	{
		$rule = new FormRule();

		$this->rules[] = $rule;

		return $rule;
	}

	public function getField(string $name): ?Field
	{
		return $this->fields[$name] ?? null;
	}

	/**
	 * @param \OZONE\OZ\Forms\FormData      $unsafe_fd
	 * @param null|\OZONE\OZ\Forms\FormData $cleaned_fd
	 * @param string                        $step_prefix
	 *
	 * @return \OZONE\OZ\Forms\FormData
	 *
	 * @throws \OZONE\OZ\Exceptions\InvalidFormException
	 */
	public function validate(FormData $unsafe_fd, ?FormData $cleaned_fd = null, string $step_prefix = ''): FormData
	{
		$this->assertValidCSRFToken($unsafe_fd);

		$cleaned_fd = $cleaned_fd ?? new FormData();

		foreach ($this->fields as $field) {
			$name = $step_prefix . $field->getName();
			if ($field->isEnabled($cleaned_fd)) {
				if ($unsafe_fd->has($name)) {
					try {
						$cleaned_fd->set($name, $field->validate($unsafe_fd->get($name), $cleaned_fd));
					} catch (TypesInvalidValueException $e) {
						/** @var InvalidFormException $e */
						$e = InvalidFormException::tryConvert($e);

						throw $e;
					}
				} elseif ($field->isRequired()) {
					throw new InvalidFormException('OZ_FORM_MISSING_REQUIRED_FIELD', ['field' => $name]);
				}
			}
		}

		foreach ($this->rules as $rule) {
			if (!$rule->check($cleaned_fd)) {
				throw new InvalidFormException($rule->getErrorMessage(), [
					// rule is prefixed by "_" because it may contain sensitive data
					'_rule' => $rule,
				]);
			}
		}

		foreach ($this->steps as $step) {
			if ($step_form = $step->getForm($cleaned_fd)) {
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
	 * @param \OZONE\OZ\Forms\Form $from
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
	 * @return \OZONE\OZ\Forms\Form
	 */
	public static function fromTable(string|Table $table): self
	{
		if (\is_string($table)) {
			$table = DbManager::getDb()
				->getTable($table);
		}

		$columns = $table->getColumns(false);

		$form = new self();

		foreach ($columns as $column) {
			$type = $column->getType();

			if ($type->isAutoIncremented()) {
				continue;
			}

			if (TypeDate::NAME === $type->getName() && true === $type->getOption('auto')) {
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
	 * @throws \OZONE\OZ\Exceptions\InvalidFormException When the CSRF token is invalid
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
