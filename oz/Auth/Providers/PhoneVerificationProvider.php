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

use InvalidArgumentException;
use OZONE\Core\App\Context;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Senders\Messages\SMSMessage;

/**
 * Class PhoneVerificationProvider.
 */
class PhoneVerificationProvider extends AuthProvider
{
	public const NAME = 'auth:provider:phone:verify';

	/**
	 * PhoneVerificationProvider constructor.
	 *
	 * @param Context $context
	 * @param string  $phone
	 */
	public function __construct(Context $context, protected string $phone)
	{
		parent::__construct($context);
	}

	/**
	 * Gets the phone.
	 *
	 * @return string
	 */
	public function getPhone(): string
	{
		return $this->phone;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get(Context $context, array $payload): self
	{
		$phone = $payload['phone'] ?? null;

		if (empty($phone)) {
			throw new InvalidArgumentException('Missing "phone" in payload.');
		}

		return new self($context, $phone);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPayload(): array
	{
		return [
			'phone' => $this->phone,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public static function getName(): string
	{
		return self::NAME;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function onInit(OZAuth $auth): void
	{
		parent::onInit($auth);

		$this->sendSms();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function onRefresh(OZAuth $auth): void
	{
		parent::onRefresh($auth);

		$this->sendSms(false);
	}

	/**
	 * @param bool $first
	 */
	private function sendSms(bool $first = true): void
	{
		$message = new SMSMessage('oz.auth.messages.sms.otpl');

		$message->inject($this->credentials->toArray())
			->addRecipient($this->phone)
			->send();

		$this->json_response->setDone()
			->setData([
				'first' => $first,
			]);
	}
}
