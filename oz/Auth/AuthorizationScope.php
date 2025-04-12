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

namespace OZONE\Core\Auth;

use InvalidArgumentException;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Interfaces\AuthAccessRightsInterface;
use OZONE\Core\Auth\Interfaces\AuthorizationScopeInterface;
use OZONE\Core\Db\OZAuth;

/**
 * Class AuthorizationScope.
 */
class AuthorizationScope implements AuthorizationScopeInterface
{
	protected string $label = '';
	protected int $try_max  = 0;
	protected int $lifetime = 0;
	protected AuthAccessRightsInterface $access_right;

	/**
	 * AuthorizationScope constructor.
	 */
	public function __construct()
	{
		$this->setTryMax((int) Settings::get('oz.auth', 'OZ_AUTH_CODE_TRY_MAX'))
			->setLifetime((int) Settings::get('oz.auth', 'OZ_AUTH_CODE_LIFE_TIME'));

		$this->access_right = new AuthAccessRights();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getLabel(): string
	{
		return $this->label;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setLabel(string $label): self
	{
		$this->label = $label;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getLifetime(): int
	{
		return $this->lifetime;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setLifetime(int $lifetime): self
	{
		if ($lifetime <= 0) {
			throw new InvalidArgumentException(\sprintf(
				'lifetime=%s should be a positive integer greater than 0.',
				$lifetime
			));
		}
		$this->lifetime = $lifetime;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTryMax(): int
	{
		return $this->try_max;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setTryMax(int $try_max): self
	{
		if ($try_max <= 0) {
			throw new InvalidArgumentException(\sprintf(
				'try_max=%s should be a positive integer greater than 0.',
				$try_max
			));
		}

		$this->try_max = $try_max;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getAccessRight(): AuthAccessRightsInterface
	{
		return $this->access_right;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setAccessRight(AuthAccessRightsInterface $access_right): self
	{
		$this->access_right = $access_right;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function from(OZAuth $auth): static
	{
		$scope = new self();

		return $scope
			->setTryMax($auth->getTryMax())
			->setLifetime($auth->getLifetime())
			->setLabel($auth->getLabel())
			->setAccessRight(AuthAccessRights::from($auth));
	}
}
