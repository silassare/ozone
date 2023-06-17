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
	 * @var callable
	 */
	protected $_builder;

	/**
	 * @var callable
	 */
	protected $_if;

	public function __construct(protected string $name, callable $builder, ?callable $if = null)
	{
		$this->_if      = $if;
		$this->_builder = $builder;
	}

	/**
	 * @param \OZONE\Core\Forms\FormData $fd
	 *
	 * @return null|\OZONE\Core\Forms\Form
	 */
	public function getForm(FormData $fd): ?Form
	{
		if ($this->_if && !\call_user_func($this->_if, $fd)) {
			return null;
		}

		$form = \call_user_func($this->_builder, $fd);

		if (null !== $form && !($form instanceof Form)) {
			throw (new RuntimeException(
				\sprintf(
					'Step form builder should return instance of "%s" or "null" not: %s',
					self::class,
					\get_debug_type($form)
				)
			))->suspectCallable($this->_builder);
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
