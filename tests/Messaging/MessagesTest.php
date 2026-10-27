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

namespace OZONE\Tests\Messaging;

use OZONE\Core\Messaging\Messages\MailMessage;
use OZONE\Core\Messaging\Messages\Message;
use OZONE\Core\Messaging\Messages\NotificationMessage;
use OZONE\Core\Messaging\Messages\SMSMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class MessagesTest.
 *
 * @internal
 */
#[CoversClass(MailMessage::class)]
#[CoversClass(Message::class)]
#[CoversClass(NotificationMessage::class)]
#[CoversClass(SMSMessage::class)]
final class MessagesTest extends TestCase
{
	public function testEachMessageClassNamesItsChannel(): void
	{
		self::assertSame('mail', new MailMessage('t')->getChannel());
		self::assertSame('sms', new SMSMessage('t')->getChannel());
		self::assertSame('notification', new NotificationMessage('t')->getChannel());
		self::assertSame('whatsapp', new Message('whatsapp', 't')->getChannel());
	}

	public function testMessageKeepsWhatItIsGiven(): void
	{
		$message = new Message('whatsapp', 'app.reminder')
			->inject(['name' => 'Jane'])
			->addRecipient('+22990000000')
			->addRecipient('+22991111111')
			->setSubject('APP_REMINDER_SUBJECT')
			->setLocale('fr')
			->once('registration:42')
			->sync();

		self::assertSame('app.reminder', $message->getTemplate());
		self::assertSame(['name' => 'Jane'], $message->getData());
		self::assertSame(['+22990000000', '+22991111111'], $message->getRecipients());
		self::assertSame('APP_REMINDER_SUBJECT', $message->getSubject());
		self::assertSame('fr', $message->getLocale());
		self::assertSame('registration:42', $message->getOnceKey());
		self::assertTrue($message->isSync());
	}

	public function testDefaults(): void
	{
		$message = new MailMessage('t');

		self::assertSame([], $message->getData());
		self::assertSame([], $message->getRecipients());
		self::assertNull($message->getSubject());
		self::assertNull($message->getLocale());
		self::assertNull($message->getOnceKey());
		self::assertFalse($message->isSync());
	}
}
