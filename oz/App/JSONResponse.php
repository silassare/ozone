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

namespace OZONE\Core\App;

use LogicException;
use Override;
use OZONE\Core\Forms\Form;
use OZONE\Core\Utils\JSONResult;

/**
 * Class JSONResponse.
 */
final class JSONResponse extends JSONResult
{
	private ?Form $form = null;

	/**
	 * Gets form.
	 *
	 * @return null|Form
	 */
	public function getForm(): ?Form
	{
		return $this->form;
	}

	/**
	 * Sets form.
	 *
	 * @param null|Form $form the form instance
	 *
	 * @return $this
	 */
	public function setForm(?Form $form): self
	{
		$this->form = $form;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function merge(JSONResult $other): static
	{
		parent::merge($other);

		if ($other instanceof self && null !== $other->form) {
			$this->form = $other->form;
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Not supported: {@see Form} instances contain callables and runtime state
	 * that are not JSON-serializable and cannot be reconstructed from a payload.
	 * JSONResponse is a transient response object, not a persistent value.
	 *
	 * @throws LogicException always
	 */
	#[Override]
	public static function revive(mixed $payload): static
	{
		throw new LogicException(
			self::class . '::revive() is not supported: Form instances contain callables and runtime state that cannot be revived from JSON.'
		);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function toArray(): array
	{
		$res = parent::toArray();

		$f = $this->form?->toArray();

		if (!empty($f)) {
			$res['form'] = $f;
		}

		return $res;
	}
}
