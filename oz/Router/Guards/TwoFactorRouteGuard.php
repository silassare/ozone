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

namespace OZONE\Core\Router\Guards;

use OZONE\Core\Auth\Auth;
use OZONE\Core\Auth\AuthState;
use OZONE\Core\Auth\Providers\UserAuthProvider;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\UnauthorizedActionException;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Router\RouteInfo;

/**
 * Class TwoFactorRouteGuard.
 */
class TwoFactorRouteGuard extends AbstractRouteGuard
{
	private FormData $form_data;

	/**
	 * @var string[]
	 */
	private array $allowed_providers;

	/**
	 * TwoFactorRouteGuard constructor.
	 *
	 * @param string ...$allowed_providers The allowed providers names
	 */
	public function __construct(string ...$allowed_providers)
	{
		$this->allowed_providers = empty($allowed_providers) ? [UserAuthProvider::NAME] : \array_unique($allowed_providers);

		$this->form_data = new FormData();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getRules(): array
	{
		return [
			'allowed_providers' => $this->allowed_providers,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public static function fromRules(array $rules): self
	{
		return new self(...$rules['allowed_providers']);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 * @throws NotFoundException
	 * @throws UnauthorizedActionException
	 */
	public function checkAccess(RouteInfo $ri): void
	{
		$context = $ri->getContext();

		$auth_ref = $context->getRequest()
			->getUnsafeFormField(OZAuth::COL_REF);

		if (empty($auth_ref)) {
			throw new ForbiddenException('OZ_2FA_REF_NOT_PROVIDED');
		}

		$auth = Auth::getRequired($auth_ref);

		if (!\in_array($auth->provider, $this->allowed_providers, true)) {
			throw new ForbiddenException('OZ_2FA_NOT_ALLOWED', [
				// don't reveal the provider to attacker,
				// it's like sending the attacker in the right direction
				'_reason'      => '2FA provider is not allowed.',
				'_allowed_2fa' => $this->allowed_providers,
				'_provider'    => $auth->provider,
			]);
		}

		$provider = Auth::provider($context, $auth);

		if (AuthState::AUTHORIZED !== $provider->getState()) {
			throw new UnauthorizedActionException();
		}

		$this->form_data->set('auth', $auth);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getFormData(): FormData
	{
		return $this->form_data;
	}
}
