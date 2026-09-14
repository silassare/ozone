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

namespace OZONE\Tests\Senders;

use Override;
use OZONE\Core\Senders\Events\SendMail;
use OZONE\Core\Senders\Events\SendNotification;
use OZONE\Core\Senders\Events\SendSMS;
use OZONE\Core\Senders\Messages\MailMessage;
use OZONE\Core\Senders\Messages\NotificationMessage;
use OZONE\Core\Senders\Messages\SMSMessage;
use PHPUnit\Framework\TestCase;

/**
 * Class MessagesTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Senders\Messages\MailMessage
 * @covers \OZONE\Core\Senders\Messages\Message
 * @covers \OZONE\Core\Senders\Messages\NotificationMessage
 * @covers \OZONE\Core\Senders\Messages\SMSMessage
 */
final class MessagesTest extends TestCase
{
	private static string $dir;

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		self::$dir = \sys_get_temp_dir() . '/oz_senders_' . \bin2hex(\random_bytes(6));

		\mkdir(self::$dir, 0o775, true);
		\file_put_contents(self::$dir . '/hello.blate', 'Hello {= name}!');
		\file_put_contents(self::$dir . '/hello.rich.blate', '<b>Hello {= name}!</b>');
	}

	#[Override]
	public static function tearDownAfterClass(): void
	{
		\unlink(self::$dir . '/hello.blate');
		\unlink(self::$dir . '/hello.rich.blate');
		\rmdir(self::$dir);

		parent::tearDownAfterClass();
	}

	public function testMessageRendersItsTemplateWithTheInjectedData(): void
	{
		$sms = (new SMSMessage(self::$dir . '/hello.blate'))->inject(['name' => 'Jane']);

		self::assertSame('Hello Jane!', \trim($sms->getContent()));
	}

	public function testMessageAttributesAndRecipients(): void
	{
		$sms = (new SMSMessage(self::$dir . '/hello.blate', ['priority' => 'high']))
			->addRecipient('+22990000000')
			->addRecipient('+22991111111');

		self::assertSame(['priority' => 'high'], $sms->getAttributes());
		self::assertSame('high', $sms->getAttribute('priority'));
		self::assertSame('low', $sms->getAttribute('missing', 'low'));
		self::assertSame(['+22990000000', '+22991111111'], $sms->getRecipients());
	}

	public function testMailRichContentFallsBackToTheTextTemplate(): void
	{
		$text_only = (new MailMessage(self::$dir . '/hello.blate'))->inject(['name' => 'Jane']);
		$rich      = (new MailMessage(self::$dir . '/hello.blate', self::$dir . '/hello.rich.blate'))
			->inject(['name' => 'Jane']);

		self::assertSame('Hello Jane!', \trim($text_only->getRichContent()));
		self::assertSame('<b>Hello Jane!</b>', \trim($rich->getRichContent()));
		self::assertSame('Hello Jane!', \trim($rich->getContent()));
	}

	public function testSendDispatchesTheMatchingEvent(): void
	{
		$sent    = [];
		$detach  = [
			SendMail::listen(static function (SendMail $e) use (&$sent): void {
				$sent[] = $e->message;
			}),
			SendSMS::listen(static function (SendSMS $e) use (&$sent): void {
				$sent[] = $e->message;
			}),
			SendNotification::listen(static function (SendNotification $e) use (&$sent): void {
				$sent[] = $e->message;
			}),
		];

		try {
			$mail         = new MailMessage(self::$dir . '/hello.blate');
			$sms          = new SMSMessage(self::$dir . '/hello.blate');
			$notification = new NotificationMessage(self::$dir . '/hello.blate');

			self::assertSame($mail, $mail->send());
			$sms->send();
			$notification->send();

			self::assertSame([$mail, $sms, $notification], $sent);
		} finally {
			foreach ($detach as $fn) {
				$fn();
			}
		}
	}
}
