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

namespace OZONE\Core\Messaging;

/**
 * Class RenderedMessage.
 *
 * A message rendered for its channel and locale: what a transport sends. The text is always there;
 * the HTML part only when the channel shows HTML and the template has one; the subject only when the
 * message has one.
 */
final class RenderedMessage
{
	/**
	 * RenderedMessage constructor.
	 *
	 * @param string      $text    the text, folded to GSM 03.38 when the channel asks for it
	 * @param null|string $html    the HTML part
	 * @param null|string $subject the subject, translated
	 */
	public function __construct(
		public readonly string $text,
		public readonly ?string $html = null,
		public readonly ?string $subject = null,
	) {}

	/**
	 * Whether there is an HTML part.
	 */
	public function hasHtml(): bool
	{
		return null !== $this->html && '' !== $this->html;
	}

	/**
	 * How many SMS segments the text takes ({@see Gsm7::segments()}), which is what an SMS costs.
	 */
	public function smsSegments(): int
	{
		return Gsm7::segments($this->text);
	}
}
