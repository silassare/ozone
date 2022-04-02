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

use OZONE\OZ\Exceptions\RuntimeException;

/**
 * Class FormStep.
 */
class FormStep
{
	/**
	 * @var callable
	 */
	protected $builder;

	/**
	 * @var callable
	 */
	protected $only_if;

	public function __construct(protected string $name, callable $builder, ?callable $only_if = null)
	{
		$this->only_if = $only_if;
		$this->builder = $builder;
	}

	/**
	 * @param \OZONE\OZ\Forms\FormData $fd
	 *
	 * @return null|\OZONE\OZ\Forms\Form
	 */
	public function getForm(FormData $fd): ?Form
	{
		if ($this->only_if && !\call_user_func($this->only_if, $fd)) {
			return null;
		}

		$form = \call_user_func($this->builder, $fd);

		if (null !== $form && !($form instanceof Form)) {
			throw (new RuntimeException(
				\sprintf(
					'Step form builder should return instance of "%s" or "null" not: %s',
					self::class,
					\get_debug_type($form)
				)
			))->suspectCallable($this->builder);
		}

		return $form;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}
}
