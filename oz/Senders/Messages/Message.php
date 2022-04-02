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

namespace OZONE\OZ\Senders\Messages;

use OZONE\OZ\FS\TemplatesUtils;

abstract class Message
{
	protected array $data = [];

	public function __construct(protected string $template, protected array $attributes = [])
	{
	}

	/**
	 * @param array $inject
	 *
	 * @return \OZONE\OZ\Senders\Messages\Message
	 */
	public function inject(array $inject): static
	{
		$this->data = $inject;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getContent(): string
	{
		return TemplatesUtils::compile($this->template, $this->data);
	}

	/**
	 * @return array
	 */
	public function getAttributes(): array
	{
		return $this->attributes;
	}

	/**
	 * @param string $name
	 * @param null   $default
	 *
	 * @return mixed
	 */
	public function getAttribute(string $name, $default = null): mixed
	{
		return $this->attributes[$name] ?? $default;
	}
}
