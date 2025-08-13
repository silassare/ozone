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

namespace OZONE\Core\Lang;

use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class I18nMessage.
 */
class I18nMessage implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	public function __construct(protected string $text, protected array $data = []) {}

	/**
	 * Gets the message text/key.
	 *
	 * @return string
	 */
	public function getText(): string
	{
		return $this->text;
	}

	/**
	 * Gets the message data.
	 *
	 * @return array
	 */
	public function getData(): array
	{
		return $this->data;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'text' => $this->text,
			'data' => $this->data,
		];
	}
}
