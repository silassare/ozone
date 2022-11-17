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

namespace OZONE\OZ\Ofv;

use Exception;
use OZONE\OZ\Exceptions\BaseException;
use OZONE\OZ\Exceptions\InvalidFormException;
use OZONE\OZ\Exceptions\RuntimeException;
use Throwable;

/**
 * Class OFormValidator.
 */
final class OFormValidator
{
	private static bool $ofv_validators_loaded = false;

	/**
	 * the form to validate.
	 */
	private array $form;

	/**
	 * should we log errors.
	 */
	private bool $log_error;

	/**
	 * the form validation rules.
	 */
	private array $rules_list = [];

	/**
	 * errors list.
	 */
	private array $errors = [];

	/**
	 * OFormValidator constructor.
	 *
	 * @param array $form      the form to validate
	 * @param bool  $log_error should we log error?
	 *
	 * @throws Exception
	 */
	public function __construct(array $form, bool $log_error = false)
	{
		$this->form      = $form;
		$this->log_error = $log_error;

		if (!self::$ofv_validators_loaded) {
			self::$ofv_validators_loaded = true;

			OFormUtils::loadValidators(OZ_OZONE_DIR . 'oz_settings' . DS . 'ofv_validators');
			OFormUtils::loadValidators(OZ_APP_DIR . 'oz_settings' . DS . 'ofv_validators', true);
		}
	}

	/**
	 * validate the form with a given rules list.
	 *
	 * @param array $rules_list the rules to use for each field
	 *
	 * @return bool
	 *
	 * @throws Throwable
	 */
	public function checkForm(array $rules_list): bool
	{
		$this->rules_list = $rules_list;

		foreach ($rules_list as $field_name => $rules) {
			$ofv_func_name = __NAMESPACE__ . '\ofv_' . $field_name;

			// does this field validator exists?
			if (\function_exists($ofv_func_name)) {
				$ofv_func_name($this);
			} else {
				$this->addError(new RuntimeException('OZ_FIELD_UNKNOWN', ['field' => $field_name]));
			}
		}

		return 0 === \count($this->errors);
	}

	/**
	 * Gets the value of a given field name.
	 *
	 * @param string $name the field name
	 *
	 * @return mixed
	 */
	public function getField(string $name): mixed
	{
		return $this->form[$name] ?? null;
	}

	/**
	 * Sets the value of a given field name.
	 *
	 * @param string $name  the field name
	 * @param mixed  $value the field value
	 *
	 * @return $this
	 */
	public function setField(string $name, mixed $value): self
	{
		$this->form[$name] = $value;

		return $this;
	}

	/**
	 * Gets the rules of a given field name.
	 *
	 * @param string $name the field name
	 *
	 * @return array
	 */
	public function getRules(string $name): array
	{
		if (!empty($this->rules_list[$name])) {
			return $this->rules_list[$name];
		}

		return [];
	}

	/**
	 * Gets the current form.
	 *
	 * @return array
	 */
	public function getForm(): array
	{
		return $this->form;
	}

	/**
	 * Gets form errors.
	 *
	 * @return array
	 */
	public function getErrors(): array
	{
		return $this->errors;
	}

	/**
	 * adds errors to invalidate this form.
	 *
	 * @param BaseException|string $e_msg  the error message
	 * @param null|array           $e_data the error data
	 *
	 * @throws Throwable
	 */
	public function addError(Throwable|string $e_msg, ?array $e_data = null): void
	{
		if ($e_msg instanceof BaseException) {
			$e = $e_msg;
		} else {
			$e = new InvalidFormException($e_msg, $e_data);
		}

		if ($this->log_error) {
			$this->errors[] = $e;
		} else {
			throw $e;
		}
	}
}
