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

namespace OZONE\Core\Auth\Providers;

use Override;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Messaging\Messages\MailMessage;

/**
 * Class EmailOwnershipVerificationProvider.
 */
final class EmailOwnershipVerificationProvider extends OwnershipVerificationProvider
{
	public const string NAME = 'auth:provider:email:verify';

	/**
	 * The email verified.
	 */
	public function getEmail(): string
	{
		return $this->identifier;
	}

	#[Override]
	public static function identifierType(): string
	{
		return AuthUserInterface::IDENTIFIER_TYPE_EMAIL;
	}

	#[Override]
	public static function getName(): string
	{
		return self::NAME;
	}

	#[Override]
	protected function send(): void
	{
		new MailMessage('oz.auth.messages.verify.email')
			->setSubject($this->purpose->text('EMAIL_SUBJECT'))
			->inject($this->messageData(['EMAIL_TITLE', 'EMAIL_BODY', 'EMAIL_BTN', 'EMAIL_IGNORE']))
			->addRecipient($this->identifier)
			->send();
	}
}
