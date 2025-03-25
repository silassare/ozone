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

use OZONE\Core\App\Context;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Senders\Messages\SMSMessage;

/**
 * Class PhoneOwnershipVerificationProvider.
 */
class PhoneOwnershipVerificationProvider extends AuthorizationProvider
{
	public const NAME = 'auth:provider:phone:verify';

	/**
	 * PhoneOwnershipVerificationProvider constructor.
	 *
	 * @param Context     $context the context
	 * @param string      $phone   the phone number to verify
	 * @param null|string $label   the label to use in the message
	 */
	public function __construct(
		Context $context,
		protected string $phone,
		protected ?string $label = null
	) {
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
	public static function resolve(Context $context, OZAuth $auth): self
	{
		$payload = $auth->getPayload();
		$phone   = $payload['phone'] ?? null;
		$label   = $payload['label'] ?? null;

		if (empty($phone)) {
			throw (new RuntimeException('Missing "phone" in payload.'))->suspectObject($payload);
		}

		return new self($context, $phone, $label);
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
		$message = new SMSMessage(
			'oz.auth.messages.verify.phone.blate',
			[
				'label' => $this->label,
			]
		);

		$message->inject($this->credentials->toArray())
			->addRecipient($this->phone)
			->send();

		$this->json_response->setDone()
			->setData([
				'first' => $first,
			]);
	}
}
