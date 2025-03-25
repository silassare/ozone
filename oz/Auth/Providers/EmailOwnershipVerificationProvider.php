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
use OZONE\Core\Senders\Messages\MailMessage;

/**
 * Class EmailOwnershipVerificationProvider.
 */
class EmailOwnershipVerificationProvider extends AuthorizationProvider
{
	public const NAME = 'auth:provider:email:verify';

	/**
	 * EmailOwnershipVerificationProvider constructor.
	 *
	 * @param Context     $context the context
	 * @param string      $email   the email to verify
	 * @param null|string $label   the label to use in the message
	 */
	public function __construct(
		Context $context,
		protected string $email,
		protected ?string $label = null
	) {
		parent::__construct($context);
	}

	/**
	 * Gets the email.
	 *
	 * @return string
	 */
	public function getEmail(): string
	{
		return $this->email;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function resolve(Context $context, OZAuth $auth): self
	{
		$payload = $auth->getPayload();
		$email   = $payload['email'] ?? null;
		$label   = $payload['label'] ?? null;

		if (empty($email)) {
			throw (new RuntimeException('Missing "email" in payload.'))->suspectObject($payload);
		}

		return new self($context, $email, $label);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPayload(): array
	{
		return [
			'email' => $this->email,
			'label' => $this->label,
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
		$this->sendMail();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function onRefresh(OZAuth $auth): void
	{
		parent::onRefresh($auth);
		$this->sendMail(false);
	}

	/**
	 * @param bool $first
	 */
	private function sendMail(bool $first = true): void
	{
		$message = new MailMessage(
			'oz.auth.messages.verify.email.blate',
			'oz.auth.messages.verify.email.rich.blate',
			[
				'label' => $this->label,
			]
		);

		$message->inject($this->credentials->toArray())
			->addRecipient($this->email)
			->send();

		$this->json_response->setDone()
			->setData([
				'first' => $first,
			]);
	}
}
