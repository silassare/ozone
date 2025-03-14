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

namespace OZONE\Core\Senders\Messages;

use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\FS\Templates;
use OZONE\Core\Senders\Events\SendMail;

/**
 * Class MailMessage.
 *
 * @extends Message<string|AuthUserInterface>
 */
class MailMessage extends Message
{
	/**
	 * MailMessage Constructor.
	 *
	 * @param string      $template
	 * @param null|string $template_rich
	 * @param array       $attributes
	 */
	public function __construct(
		string $template,
		private readonly ?string $template_rich = null,
		array $attributes = []
	) {
		parent::__construct($template, $attributes);
	}

	/**
	 * Gets the rich content.
	 *
	 * @return string
	 */
	public function getRichContent(): string
	{
		return Templates::compile($this->template_rich ?? $this->template, $this->data);
	}

	/**
	 * {@inheritDoc}
	 */
	public function send(): static
	{
		(new SendMail($this))->dispatch();

		return $this;
	}
}
