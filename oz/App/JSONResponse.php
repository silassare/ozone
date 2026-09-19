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
use OZONE\Core\Exceptions\BaseException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Utils\JSONResult;

/**
 * Class JSONResponse.
 */
final class JSONResponse extends JSONResult
{
	private ?Form $form = null;

	/**
	 * The envelope a client reads, whatever built it.
	 *
	 * Every JSON answer of OZone has this shape: `error`, `msg`, `data`, the form of a discovery or of
	 * a resumable step, the server time (`utime`), and the session expiry (`stime`) when the request
	 * was an authenticated stateful one. A service answers through {@see Service::respond()}, a failure
	 * through {@see BaseException}, and anything else builds one here rather
	 * than writing its own object.
	 *
	 * @param null|Context $context the request being answered, for the session expiry
	 *
	 * @return array<string, mixed>
	 */
	public function toEnvelope(?Context $context = null): array
	{
		$data          = $this->toArray();
		$now           = \time();
		$data['utime'] = $now;

		if (null !== $context && $context->hasAuthenticatedUser() && $context->hasStatefulAuth()) {
			$data['stime'] = $now + $context->requireStatefulAuth()
				->lifetime();
		}

		return $data;
	}

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
	public function setForm(?Form $form): static
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
			self::class . '::revive() is not supported: Form instances contain callables and runtime state'
				. ' that cannot be revived from JSON.'
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
