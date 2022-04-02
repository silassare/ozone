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
use OZONE\OZ\Senders\Events\SendMail;
use PHPUtils\Events\Event;

class MailMessage extends Message
{
	public function __construct(
		string $template,
		private ?string $template_rich = null,
		array $attributes = []
	) {
		parent::__construct($template, $attributes);
	}

	/**
	 * @return string
	 */
	public function getRichContent(): string
	{
		return TemplatesUtils::compile($this->template_rich ?? $this->template, $this->data);
	}

	/**
	 * @param string $email
	 *
	 * @return $this
	 */
	public function sendTo(string $email): static
	{
		Event::trigger(new SendMail($email, $this));

		return $this;
	}
}
