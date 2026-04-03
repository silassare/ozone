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

use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\ORM\Exceptions\ORMException;
use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Auth;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Enums\AuthorizationSecretType;
use OZONE\Core\Auth\Enums\AuthorizationState;
use OZONE\Core\Auth\Events\AuthUserLoggedIn;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\TwoFactor\Channels\TotpChannel;
use OZONE\Core\Auth\TwoFactor\TOTP;
use OZONE\Core\Auth\TwoFactor\TwoFactorChannelRegistry;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Exceptions\UnauthorizedException;
use OZONE\Core\Senders\Messages\MailMessage;
use OZONE\Core\Senders\Messages\SMSMessage;

/**
 * Class TwoFactorAuthorizationProvider.
 *
 * Handles the 2FA step that follows a successful password-based login.
 *
 * The provider stores the chosen channel and the auth-method state ID in the OZAuth
 * payload.  On successful verification it recovers the pending user from the session
 * store and attaches them, completing the login flow.
 *
 * Supported channels (extensible via {@see TwoFactorChannelRegistry}):
 * - 'email' — OTP delivered by email
 * - 'sms'   — OTP delivered by SMS
 * - 'totp'  — verified locally against the user's TOTP secret (no message sent)
 */
final class TwoFactorAuthorizationProvider extends AuthorizationProvider
{
	public const NAME = 'auth:provider:2fa';

	protected Context $context;

	/**
	 * TwoFactorAuthorizationProvider constructor.
	 *
	 * @param Context           $context  the current request context
	 * @param AuthUserInterface $user     the user that needs to complete 2FA
	 * @param string            $channel  channel name (e.g. 'email', 'sms', 'totp')
	 * @param string            $state_id the stateful auth method state ID (session ID) at login time
	 */
	public function __construct(
		Context $context,
		protected AuthUserInterface $user,
		protected string $channel,
		protected string $state_id
	) {
		parent::__construct($context);

		$this->context = $context;

		// Override the default scope lifetime and try max with 2FA-specific settings.
		$this->scope->setLifetime((int) Settings::get('oz.auth.2fa', 'OZ_2FA_CODE_LIFE_TIME'));
		$this->scope->setTryMax((int) Settings::get('oz.auth.2fa', 'OZ_2FA_CODE_TRY_MAX'));
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function getName(): string
	{
		return self::NAME;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function resolve(Context $context, OZAuth $auth): static
	{
		$payload  = $auth->getPayload();
		$channel  = $payload['channel'] ?? '';
		$state_id = $payload['state_id'] ?? '';

		$user = AuthUsers::identifyBySelector([
			AuthUsers::FIELD_AUTH_USER_TYPE => $auth->getOwnerType(),
			AuthUsers::FIELD_AUTH_USER_ID   => $auth->getOwnerId(),
		]);

		if (!$user) {
			throw (new RuntimeException('Unable to identify the owner of this 2FA auth flow.'))->suspectObject($auth);
		}

		return new self($context, $user, $channel, $state_id);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getPayload(): array
	{
		return [
			'channel'  => $this->channel,
			'state_id' => $this->state_id,
		];
	}

	/**
	 * Overrides parent to handle TOTP channel verification.
	 *
	 * For email/sms channels the standard OZAuth hash-comparison is used.
	 * For the TOTP channel the submitted code is verified against the user's TOTP
	 * secret in real time — without any stored hash comparison.
	 *
	 * {@inheritDoc}
	 *
	 * @throws InvalidFormException
	 * @throws NotFoundException
	 * @throws UnauthorizedException
	 */
	#[Override]
	public function authorize(AuthorizationSecretType $type): void
	{
		if (TotpChannel::getName() !== $this->channel) {
			parent::authorize($type);

			return;
		}

		$this->authorizeTotp($type);
	}

	/**
	 * {@inheritDoc}
	 *
	 * For email/sms: sends the OTP code on init.
	 * For totp: no message is sent — the user reads the code from their authenticator app.
	 */
	#[Override]
	protected function onInit(OZAuth $auth): void
	{
		parent::onInit($auth);

		$this->json_response->setData([
			'two_fa_required' => true,
			'channel'         => $this->channel,
		]);

		match ($this->channel) {
			'email' => $this->sendEmail(),
			'sms'   => $this->sendSms(),
			default => null, // totp or any custom channel: nothing to send
		};
	}

	/**
	 * {@inheritDoc}
	 *
	 * For email/sms: resends the OTP code.
	 * For totp: nothing to do.
	 */
	#[Override]
	protected function onRefresh(OZAuth $auth): void
	{
		parent::onRefresh($auth);

		match ($this->channel) {
			'email' => $this->sendEmail(false),
			'sms'   => $this->sendSms(false),
			default => null,
		};
	}

	/**
	 * {@inheritDoc}
	 *
	 * Re-attaches the pending user to the auth method, completing the login flow.
	 */
	#[Override]
	protected function onAuthorized(OZAuth $auth): void
	{
		parent::onAuthorized($auth);

		$auth_method = $this->context->requireStatefulAuth();

		// Verify the auth method state matches the one recorded at login time.
		// This prevents a different session from completing another session's 2FA.
		if ($this->state_id !== $auth_method->stateID()) {
			throw new UnauthorizedException('OZ_2FA_SESSION_MISMATCH', [
				'_debug' => [OZAuth::COL_REF => $auth->getRef()],
			]);
		}

		$store        = $auth_method->store();
		$pending_user = $store->get2FAPendingUser();

		if (!$pending_user) {
			throw new UnauthorizedException('OZ_2FA_NO_PENDING_USER', [
				'_debug' => [OZAuth::COL_REF => $auth->getRef()],
			]);
		}

		// Set the guard flag BEFORE re-attaching so the AuthUserLoggedIn listener
		// skips the 2FA check for this re-dispatch.
		$store->set('oz.2fa.verified', true);
		$store->clear2FAPendingUser();

		$auth_method->attachAuthUser($pending_user);

		(new AuthUserLoggedIn($this->context, $pending_user))->dispatch();
	}

	/**
	 * Handles TOTP-specific authorization (replaces hash-based comparison with TOTP verification).
	 *
	 * @param AuthorizationSecretType $type
	 *
	 * @throws InvalidFormException
	 * @throws NotFoundException
	 * @throws UnauthorizedException
	 */
	private function authorizeTotp(AuthorizationSecretType $type): void
	{
		$ref  = $this->credentials->getReference();
		$code = match ($type) {
			AuthorizationSecretType::CODE  => $this->credentials->getCode(),
			AuthorizationSecretType::TOKEN => $this->credentials->getToken(),
		};

		if (empty($ref)) {
			throw new InvalidFormException('OZ_AUTH_MISSING_REF');
		}

		if (empty($code)) {
			throw new InvalidFormException('OZ_AUTH_MISSING_SECRET');
		}

		$auth = Auth::getRequired($ref);

		$this->scope = $this->scope::from($auth);

		$try_max   = $auth->getTryMax(); // 0 means unlimited
		$count     = $auth->getTryCount() + 1;
		$remainder = $try_max - $count;

		// Check expiry.
		if ($auth->getExpireAt() <= \time()) {
			$this->save($auth->setState(AuthorizationState::REFUSED->value));
			$this->onExpired($auth);

			return;
		}

		$secret = $this->user->getAuthUserDataStore()->get2FATotpSecret();

		if (empty($secret)) {
			throw new RuntimeException('TOTP secret is not configured for this user.', [
				'_user_type' => $this->user->getAuthUserType(),
				'_user_id'   => $this->user->getAuthIdentifier(),
			]);
		}

		/** @var int $window */
		$window = Settings::get('oz.auth.2fa', 'OZ_2FA_TOTP_WINDOW');

		/** @var int $digits */
		$digits = Settings::get('oz.auth.2fa', 'OZ_2FA_TOTP_DIGITS');

		/** @var int $step */
		$step = Settings::get('oz.auth.2fa', 'OZ_2FA_TOTP_STEP');

		$is_valid = TOTP::verify($secret, $code, 0, $window, $digits, $step);

		if ($is_valid) {
			try {
				$auth->setState(AuthorizationState::AUTHORIZED->value);
				$auth->save();
			} catch (CRUDException|ORMException $e) {
				throw new RuntimeException('Unable to save auth entity data.', null, $e);
			}
			$this->onAuthorized($auth);
		} elseif (0 === $try_max || $remainder <= 0) {
			try {
				$auth->setState(AuthorizationState::REFUSED->value);
				$auth->save();
			} catch (CRUDException|ORMException $e) {
				throw new RuntimeException('Unable to save auth entity data.', null, $e);
			}
			$this->onTooMuchRetry($auth);
		} else {
			$auth->setTryCount($auth->getTryCount() + 1);

			try {
				$auth->save();
			} catch (CRUDException|ORMException $e) {
				throw new RuntimeException('Unable to save auth entity data.', null, $e);
			}
			$this->onInvalidCode($auth);
		}
	}

	/**
	 * Sends the OTP code via email.
	 *
	 * @param bool $first true when the code is sent for the first time
	 */
	private function sendEmail(bool $first = true): void
	{
		$email = $this->user->getAuthIdentifiers()[AuthUserInterface::IDENTIFIER_TYPE_EMAIL] ?? '';

		if (empty($email)) {
			throw new RuntimeException('Cannot send 2FA email: user has no email address.', [
				'_user_type' => $this->user->getAuthUserType(),
				'_user_id'   => $this->user->getAuthIdentifier(),
			]);
		}

		$message = new MailMessage(
			'oz.auth.messages.2fa.email.blate',
			'oz.auth.messages.2fa.email.rich.blate'
		);

		$message->inject($this->credentials->toArray())
			->addRecipient($email)
			->send();

		$this->json_response->setData(['first' => $first]);
	}

	/**
	 * Sends the OTP code via SMS.
	 *
	 * @param bool $first true when the code is sent for the first time
	 */
	private function sendSms(bool $first = true): void
	{
		$phone = $this->user->getAuthIdentifiers()[AuthUserInterface::IDENTIFIER_TYPE_PHONE] ?? '';

		if (empty($phone)) {
			throw new RuntimeException('Cannot send 2FA SMS: user has no phone number.', [
				'_user_type' => $this->user->getAuthUserType(),
				'_user_id'   => $this->user->getAuthIdentifier(),
			]);
		}

		$message = new SMSMessage('oz.auth.messages.2fa.sms.blate');

		$message->inject($this->credentials->toArray())
			->addRecipient($phone)
			->send();

		$this->json_response->setData(['first' => $first]);
	}
}
