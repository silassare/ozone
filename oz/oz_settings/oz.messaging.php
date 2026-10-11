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

use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Messaging\Channel;
use OZONE\Core\Messaging\Messages\MailMessage;
use OZONE\Core\Messaging\Messages\NotificationMessage;
use OZONE\Core\Messaging\Messages\SMSMessage;
use OZONE\Core\Messaging\Messenger;
use OZONE\Core\Messaging\Transports\LogTransport;
use OZONE\Core\Messaging\Transports\TransportInterface;

/**
 * The messaging channels and what carries each one ({@see Messenger}). Every message is recorded in
 * `oz_messages`.
 */
return [
	/**
	 * The channels, by name. A project overrides one option of a channel (its transport, say) and keeps
	 * the others, and declares channels of its own (`whatsapp`); what a channel leaves out takes the
	 * value of {@see Channel::DEFAULTS}:
	 *
	 * - `enabled`: false records its messages as skipped instead of sending them;
	 * - `transport`: a {@see TransportInterface}; {@see LogTransport}, the default, writes messages to the
	 *   log and is unavailable in production, where `oz doctor check` fails while an enabled channel
	 *   uses it;
	 * - `async`: true delivers through a queue job, run once the response is sent and by the
	 *   `oz:messages` cron task; false delivers before `send()` returns;
	 * - `retry_max`, `retry_delay`: the attempts of a queued message, and the seconds between two;
	 * - `identifier`: the identifier of a user recipient that is the destination
	 *   (`AuthUserInterface::IDENTIFIER_TYPE_*`);
	 * - `html`: whether an HTML part is rendered (`{template}.html.blate`);
	 * - `gsm7`: whether the text is folded to the GSM 03.38 alphabet, which keeps an SMS at 160
	 *   characters instead of 70.
	 */
	'OZ_MESSAGING_CHANNELS' => [
		MailMessage::CHANNEL         => [
			'transport'  => LogTransport::class,
			'identifier' => AuthUserInterface::IDENTIFIER_TYPE_EMAIL,
			'html'       => true,
		],
		SMSMessage::CHANNEL          => [
			'transport'  => LogTransport::class,
			'identifier' => AuthUserInterface::IDENTIFIER_TYPE_PHONE,
		],
		NotificationMessage::CHANNEL => [
			'transport'  => LogTransport::class,
			'identifier' => AuthUserInterface::IDENTIFIER_TYPE_ID,
		],
	],

	/**
	 * The queue of the messages delivered by a job.
	 */
	'OZ_MESSAGING_QUEUE'    => 'oz:messages',

	/**
	 * The look of the emails, which carry inline styles (mail clients ignore CSS variables): `color`
	 * (buttons and links), `logo_url` (an absolute URL; the initial of the name when null) and `name`
	 * (the product, `OZ_PROJECT_NAME` when null). A full redesign overrides
	 * `oz.mail.layout.html.blate` in the project's templates.
	 */
	'OZ_MESSAGING_MAIL_THEME' => [
		'color'    => '#c4203d',
		'logo_url' => null,
		'name'     => null,
	],

	/**
	 * The domain of the site a one-time code is entered on (`app.example.com`): when set, an SMS
	 * carrying a code ends with `@<domain> #<code>`, which lets browsers and phones fill the code in
	 * (WebOTP, the iOS and Android one-time code). Null leaves the line out.
	 */
	'OZ_MESSAGING_OTP_DOMAIN' => null,

	/**
	 * How many days a settled record (sent, failed, skipped) of `oz_messages` is kept once settled,
	 * before the hourly garbage collection (`oz:gc`) deletes it; null keeps every one. Queued records
	 * are never deleted.
	 */
	'OZ_MESSAGING_RETENTION' => 90,

	/**
	 * The same for the records of messages sent once (`Message::once()`): deleting one lets that
	 * message be sent again for its key, so they are kept until a project says otherwise (null).
	 */
	'OZ_MESSAGING_ONCE_RETENTION' => null,
];
