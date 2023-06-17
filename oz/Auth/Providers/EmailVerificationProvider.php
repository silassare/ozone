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
use OZONE\Core\Senders\Messages\MailMessage;

/**
 * Class EmailVerificationProvider.
 */
class EmailVerificationProvider extends AuthProvider
{
	public const NAME = 'auth:provider:email:verify';

	/**
	 * EmailVerificationProvider constructor.
	 *
	 * @param \OZONE\Core\App\Context $context
	 * @param string                  $email
	 */
	public function __construct(Context $context, protected string $email)
	{
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
	public static function get(Context $context, array $payload): self
	{
		$email = $payload['email'] ?? null;

		if (empty($email)) {
			throw new InvalidArgumentException('Missing "email" in payload.');
		}

		return new self($context, $email);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPayload(): array
	{
		return [
			'email' => $this->email,
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
		$message = new MailMessage('oz.auth.message.mail.otpl', 'oz.auth.message.mail.rich.otpl');

		$message->inject($this->credentials->toArray())
			->send($this->email);

		$this->json_response->setDone()
			->setData([
				'first' => $first,
			]);
	}
}
