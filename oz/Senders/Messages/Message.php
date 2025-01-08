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

use OZONE\Core\FS\Templates;
use OZONE\Core\Senders\Interfaces\MessageInterface;

/**
 * Class Message.
 *
 * @template T
 */
abstract class Message implements MessageInterface
{
	protected array $data = [];

	/**
	 * The recipients of this message.
	 *
	 * @var T[]
	 */
	protected array $recipients = [];

	/**
	 * Message Constructor.
	 *
	 * @param string $template
	 * @param array  $attributes
	 */
	public function __construct(
		protected string $template,
		protected array $attributes = []
	) {}

	/**
	 * Adds a recipient.
	 *
	 * @param T $recipient
	 */
	public function addRecipient($recipient): static
	{
		$this->recipients[] = $recipient;

		return $this;
	}

	/**
	 * Gets the recipients.
	 *
	 * @return T[]
	 */
	public function getRecipients(): array
	{
		return $this->recipients;
	}

	/**
	 * {@inheritDoc}
	 */
	public function inject(array $inject): static
	{
		$this->data = $inject;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getContent(): string
	{
		return Templates::compile($this->template, $this->data);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAttributes(): array
	{
		return $this->attributes;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAttribute(string $name, $default = null): mixed
	{
		return $this->attributes[$name] ?? $default;
	}
}
