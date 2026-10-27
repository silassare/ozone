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
use OZONE\Core\Messaging\Messages\SMSMessage;

/**
 * Class PhoneOwnershipVerificationProvider.
 */
final class PhoneOwnershipVerificationProvider extends OwnershipVerificationProvider
{
	public const string NAME = 'auth:provider:phone:verify';

	/**
	 * The phone verified.
	 */
	public function getPhone(): string
	{
		return $this->identifier;
	}

	#[Override]
	public static function identifierType(): string
	{
		return AuthUserInterface::IDENTIFIER_TYPE_PHONE;
	}

	#[Override]
	public static function getName(): string
	{
		return self::NAME;
	}

	#[Override]
	protected function send(): void
	{
		new SMSMessage('oz.auth.messages.verify.phone')
			->inject($this->messageData(['PHONE_SMS']))
			->addRecipient($this->identifier)
			->send();
	}
}
