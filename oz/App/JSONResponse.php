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

use Override;
use OZONE\Core\Forms\Form;
use OZONE\Core\Utils\JSONResult;

/**
 * Class JSONResponse.
 */
final class JSONResponse extends JSONResult
{
	/**
	 * Gets form.
	 *
	 * @return null|Form
	 */
	public function getForm(): ?Form
	{
		return $this->payload['form'] ?? null;
	}

	/**
	 * Sets form.
	 *
	 * @param null|Form $form
	 *
	 * @return $this
	 */
	public function setForm(?Form $form): self
	{
		$this->payload['form'] = $form;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function toArray(): array
	{
		$res = parent::toArray();

		if (empty($res['form'])) {
			unset($res['form']);
		}

		return $res;
	}
}
