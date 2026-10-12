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

namespace OZONE\Core\Auth\Views;

use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Auth;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Enums\AuthorizationSecretType;
use OZONE\Core\Auth\Enums\AuthorizationState;
use OZONE\Core\Auth\Enums\VerificationPurpose;
use OZONE\Core\Auth\Interfaces\AuthorizationProviderInterface;
use OZONE\Core\Auth\Providers\OwnershipVerificationProvider;
use OZONE\Core\Auth\VerificationPolicy;
use OZONE\Core\Columns\Types\TypePassword;
use OZONE\Core\Crypt\Password;
use OZONE\Core\Exceptions\BaseException;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\UnauthorizedException;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Users\UsersRepository;
use OZONE\Core\Web\WebView;

/**
 * Class AuthLinkView.
 *
 * Authorization links are opened with a GET, which only shows a confirmation page;
 * the authorization happens on the POST it submits. Mail scanners and link previews
 * fetch links with GET, so authorizing there would let them consume the token
 * before the user does.
 *
 * The page says what confirming grants. The link of an account recovery grants nothing to whoever
 * started it: it opens a form that sets the new password right there, so only the owner of the email
 * recovers the account with it.
 */
final class AuthLinkView extends WebView
{
	public const AUTH_LINK_ROUTE         = 'oz:auth_link';
	public const AUTH_LINK_CONFIRM_ROUTE = 'oz:auth_link:confirm';

	/**
	 * Shows the confirmation page, or the new password form of an account recovery, without touching
	 * the token; a link unknown, expired or already answered shows the page of a link that no longer
	 * works, rather than a page whose button, or whose form, could only fail.
	 */
	public function confirm(RouteInfo $ri): Response
	{
		try {
			$provider = Auth::provider($ri->getContext(), Auth::getRequired($ri->param('ref')));

			// Throws past the expiry, which the row outlives until the garbage collection.
			if (AuthorizationState::PENDING !== $provider->getState()) {
				throw new UnauthorizedException('OZ_AUTH_CODE_INVALID');
			}
		} catch (NotFoundException|UnauthorizedException $e) {
			return $this->invalid($e);
		}

		if (self::isRecovery($provider)) {
			return $this->recoveryForm($provider);
		}

		$message = $provider instanceof OwnershipVerificationProvider
			? $provider->getPurpose()->text('LINK')
			: 'OZ_VIEW_AUTH_LINK_CONFIRM_MESSAGE';

		return $this->setTemplate('oz.auth.link.confirm.blate')
			->inject([
				'oz_link_message' => $message,
				'oz_vars'         => self::textVars($provider),
			])
			->respond();
	}

	/**
	 * Authorizes with the link token, or sets the new password of an account recovery; a link
	 * unknown, used or expired shows the page of a link that no longer works.
	 */
	public function authorize(RouteInfo $ri): Response
	{
		$ref   = $ri->param('ref');
		$token = $ri->param('token');

		try {
			$auth = Auth::getRequired($ref);

			$provider = Auth::provider($ri->getContext(), $auth);

			if (self::isRecovery($provider)) {
				return $this->recover($ri, $provider, $token);
			}

			$type = AuthorizationSecretType::TOKEN;
			$provider->getCredentials()
				->setToken($token);

			$provider->authorize($type);
		} catch (ForbiddenException|InvalidFormException|NotFoundException|UnauthorizedException $e) {
			return $this->invalid($e);
		}

		return $this->setTemplate('oz.auth.link.view.blate')
			->inject(['oz_auth_link_done' => true])
			->respond();
	}

	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$router
			->get('/auth/link/:ref/:token', static function (RouteInfo $ri) {
				return (new self($ri))->confirm($ri);
			})
			->name(self::AUTH_LINK_ROUTE);

		$router
			->post('/auth/link/:ref/:token', static function (RouteInfo $ri) {
				return (new self($ri))->authorize($ri);
			})
			->name(self::AUTH_LINK_CONFIRM_ROUTE);
	}

	/**
	 * Sets the new password the owner chose on the page, with the token of the link: the token is
	 * spent, and the authorization deleted, so the one who started the recovery is granted nothing.
	 *
	 * @throws ForbiddenException
	 * @throws InvalidFormException
	 * @throws NotFoundException
	 * @throws UnauthorizedException
	 */
	private function recover(RouteInfo $ri, OwnershipVerificationProvider $provider, string $token): Response
	{
		$pass    = $ri->getUnsafeFormField('pass');
		$pass    = \is_string($pass) ? $pass : '';
		$confirm = $ri->getUnsafeFormField('pass_confirm');

		try {
			(new TypePassword())->validate($pass);
		} catch (TypesInvalidValueException $e) {
			return $this->recoveryForm($provider, $e->getMessage());
		}

		if ($pass !== $confirm) {
			return $this->recoveryForm($provider, 'OZ_VIEW_AUTH_LINK_RECOVERY_MISMATCH');
		}

		$user_type = $provider->getUserType() ?? UsersRepository::DEFAULT_USER_TYPE;
		$allowed   = VerificationPolicy::providersFor(VerificationPolicy::ACCOUNT_RECOVERY, $user_type);

		if (!\in_array($provider::getName(), $allowed, true)) {
			throw new ForbiddenException(null, [
				'_reason'    => 'Auth provider is not allowed to recover this user type.',
				'_user_type' => $user_type,
			]);
		}

		$user = AuthUsers::identifyBySelector([
			AuthUsers::FIELD_AUTH_USER_TYPE             => $user_type,
			AuthUsers::FIELD_AUTH_USER_IDENTIFIER_TYPE  => $provider::identifierType(),
			AuthUsers::FIELD_AUTH_USER_IDENTIFIER_VALUE => $provider->getIdentifier(),
		]);

		if (!$user || !$user->isAuthUserValid()) {
			throw new ForbiddenException(null, ['_reason' => 'No valid account for this identifier.']);
		}

		// Before the token is spent, so the owner may choose another one.
		if (Password::verify($pass, $user->getAuthPassword())) {
			return $this->recoveryForm($provider, 'OZ_PASSWORD_SAME_OLD_AND_NEW_PASS');
		}

		$provider->getCredentials()
			->setToken($token);
		$provider->redeem(AuthorizationSecretType::TOKEN);

		AuthUsers::updatePassword($user, $pass);

		return $this->setTemplate('oz.auth.link.recovery.blate')
			->inject(['oz_recovery_done' => true])
			->respond();
	}

	/**
	 * The new password form of an account recovery, with the error of the last try.
	 */
	private function recoveryForm(OwnershipVerificationProvider $provider, ?string $error = null): Response
	{
		$response = $this->setTemplate('oz.auth.link.recovery.blate')
			->inject([
				'oz_recovery_done'  => false,
				'oz_recovery_error' => $error,
				'oz_vars'           => self::textVars($provider),
			])
			->respond();

		return null === $error ? $response : $response->withStatus(400);
	}

	/**
	 * The page of a link that no longer works (unknown, used or expired), with the status of why.
	 */
	private function invalid(BaseException $e): Response
	{
		return $this->setTemplate('oz.auth.link.view.blate')
			->inject(['oz_auth_link_done' => false])
			->respond()
			->withStatus($e->getHTTPStatusCode());
	}

	/**
	 * Whether the link is the one of an account recovery.
	 *
	 * @psalm-assert-if-true OwnershipVerificationProvider $provider
	 */
	private static function isRecovery(AuthorizationProviderInterface $provider): bool
	{
		return $provider instanceof OwnershipVerificationProvider
			&& VerificationPurpose::ACCOUNT_RECOVERY === $provider->getPurpose();
	}

	/**
	 * What the texts of the page are filled with.
	 *
	 * @return array<string, string>
	 */
	private static function textVars(AuthorizationProviderInterface $provider): array
	{
		return [
			'oz_name'    => (string) Settings::get('oz.config', 'OZ_PROJECT_NAME', ''),
			'identifier' => $provider instanceof OwnershipVerificationProvider ? $provider->getIdentifier() : '',
		];
	}
}
