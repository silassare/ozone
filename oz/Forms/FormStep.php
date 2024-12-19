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

use OZONE\Core\Exceptions\RuntimeException;

/**
 * Class FormStep.
 */
class FormStep
{
	/**
	 * @var callable(FormValidationContext):(null|Form)
	 */
	protected $factory;

	/**
	 * @var null|callable(FormValidationContext):bool
	 */
	protected $t_if;

	/**
	 * FormStep constructor.
	 *
	 * @param string                                      $name
	 * @param callable(FormValidationContext):(null|Form) $factory
	 * @param null|callable(FormValidationContext):bool   $if
	 */
	public function __construct(protected string $name, callable $factory, ?callable $if = null)
	{
		$this->t_if    = $if;
		$this->factory = $factory;
	}

	/**
	 * Builds this step form.
	 *
	 * @param FormValidationContext $fvc
	 *
	 * @return null|Form
	 */
	public function build(FormValidationContext $fvc): ?Form
	{
		if ($this->t_if && !\call_user_func($this->t_if, $fvc)) {
			return null;
		}

		$form = \call_user_func($this->factory, $fvc);

		if (null !== $form && !($form instanceof Form)) {
			throw (new RuntimeException(
				\sprintf(
					'Step form builder should return instance of "%s" or "null" not: %s',
					self::class,
					\get_debug_type($form)
				)
			))->suspectCallable($this->factory);
		}

		return $form;
	}

	/**
	 * Gets the step name.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}
}
