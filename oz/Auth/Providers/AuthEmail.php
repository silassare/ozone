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

namespace OZONE\OZ\Auth\Providers;

use OZONE\OZ\Auth\Interfaces\AuthScopeInterface;
use OZONE\OZ\Core\Context;
use OZONE\OZ\Db\OZAuth;
use OZONE\OZ\Senders\Messages\MailMessage;

/**
 * Class AuthEmail.
 */
class AuthEmail extends AuthProvider
{
	public const NAME = 'auth:provider:email';

	/**
	 * {@inheritDoc}
	 */
	public static function getInstance(Context $context, ?AuthScopeInterface $scope = null): self
	{
		return new self($context, $scope);
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
		$email = $this->scope->getValue();

		$message = new MailMessage('auth.message.mail.otpl', 'auth.message.mail.rich.otpl');

		$message->inject($this->credentials->toArray())
			->send($email);

		$this->json_response->setDone()
			->setData([
				'first' => $first,
			]);
	}
}
