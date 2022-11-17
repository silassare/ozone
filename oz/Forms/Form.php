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

use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use OZONE\OZ\CSRF\CSRF;
use OZONE\OZ\Exceptions\InvalidFormException;
use OZONE\OZ\Http\Request;
use OZONE\OZ\Http\Uri;
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
	 * @return \OZONE\OZ\Forms\Form
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
						/** @noinspection PhpUnnecessaryLocalVariableInspection */
						$e = InvalidFormException::tryConvert($e);

						throw $e;
					}
				} elseif ($field->isRequired()) {
					throw new InvalidFormException('OZ_FORM_MISSING_REQUIRED_FIELD', ['field' => $name]);
				}
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
