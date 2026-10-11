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

namespace OZONE\Core\Messaging\Messages;

use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Db\OZMessage;
use OZONE\Core\Messaging\Messenger;

/**
 * Class Message.
 *
 * A message for one channel (`oz.messaging`), to one or more recipients. {@see send()} records it
 * once per recipient in `oz_messages` and hands it to the channel's transport, now or through the
 * queue: {@see Messenger}.
 *
 * The template is a name without extension, rendered for the channel and the locale from the first
 * of these that exists, `{lang}` left out when no locale is known:
 *
 * - `{template}.{lang}.{channel}.blate`, `{template}.{channel}.blate`,
 *   `{template}.{lang}.blate`, `{template}.blate`: the text, required;
 * - the same names ending in `.html.blate`: the HTML part, optional, for a channel whose settings
 *   say it shows HTML (`mail`).
 *
 * The text is never derived from the HTML. A text template prints its values raw (`{= link}`): it is
 * not HTML, and an escaped `&` would break a link.
 *
 * The data is rendered when the message is delivered, maybe in another process: it must be
 * serializable to JSON. It is kept encrypted in the queue, and never in `oz_messages`.
 */
class Message
{
	/**
	 * @var array<string, mixed>
	 */
	protected array $data = [];

	/**
	 * @var list<AuthUserInterface|string>
	 */
	protected array $recipients = [];

	protected ?string $subject  = null;
	protected ?string $locale   = null;
	protected ?string $once_key = null;
	protected bool $sync        = false;

	/**
	 * Message constructor.
	 *
	 * @param string $channel  the channel, as declared in `oz.messaging`
	 * @param string $template the template name, without extension
	 */
	public function __construct(
		protected readonly string $channel,
		protected readonly string $template,
	) {}

	/**
	 * Gets the channel.
	 */
	public function getChannel(): string
	{
		return $this->channel;
	}

	/**
	 * Gets the template name.
	 */
	public function getTemplate(): string
	{
		return $this->template;
	}

	/**
	 * Sets the data the templates are rendered with.
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return $this
	 */
	public function inject(array $data): static
	{
		$this->data = $data;

		return $this;
	}

	/**
	 * Gets the data the templates are rendered with.
	 *
	 * @return array<string, mixed>
	 */
	public function getData(): array
	{
		return $this->data;
	}

	/**
	 * Adds a recipient: a user, whose identifier the channel names (`identifier` in `oz.messaging`)
	 * is the destination, or the destination itself.
	 *
	 * @return $this
	 */
	public function addRecipient(AuthUserInterface|string $recipient): static
	{
		$this->recipients[] = $recipient;

		return $this;
	}

	/**
	 * Gets the recipients.
	 *
	 * @return list<AuthUserInterface|string>
	 */
	public function getRecipients(): array
	{
		return $this->recipients;
	}

	/**
	 * Sets the subject, for a channel that shows one: a lang key, translated in the message's locale
	 * with its data, or a text.
	 *
	 * @return $this
	 */
	public function setSubject(?string $subject): static
	{
		$this->subject = $subject;

		return $this;
	}

	/**
	 * Gets the subject.
	 */
	public function getSubject(): ?string
	{
		return $this->subject;
	}

	/**
	 * Sets the language the message is rendered in; the current request's language when not set.
	 *
	 * @return $this
	 */
	public function setLocale(?string $locale): static
	{
		$this->locale = $locale;

		return $this;
	}

	/**
	 * Gets the language set with {@see setLocale()}.
	 */
	public function getLocale(): ?string
	{
		return $this->locale;
	}

	/**
	 * Sends this template once only for what `$key` names (a registration, an order, ...): a message of
	 * the same template, on the same channel, with the same key is not sent again, whoever sends it.
	 *
	 * A message sent once has one recipient: with several, {@see send()} throws.
	 *
	 * @return $this
	 */
	public function once(string $key): static
	{
		$this->once_key = $key;

		return $this;
	}

	/**
	 * Gets the key set with {@see once()}.
	 */
	public function getOnceKey(): ?string
	{
		return $this->once_key;
	}

	/**
	 * Delivers in this process, before {@see send()} returns, whatever the channel's `async` setting:
	 * a failure then throws, after it is recorded.
	 *
	 * @return $this
	 */
	public function sync(bool $sync = true): static
	{
		$this->sync = $sync;

		return $this;
	}

	/**
	 * Whether {@see sync()} was asked for.
	 */
	public function isSync(): bool
	{
		return $this->sync;
	}

	/**
	 * Records the message for each recipient, and delivers or queues it.
	 *
	 * @return list<OZMessage> the records written; a message already sent once is not among them
	 */
	public function send(): array
	{
		return Messenger::send($this);
	}
}
