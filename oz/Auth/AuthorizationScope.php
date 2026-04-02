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
use Override;
use OZONE\Core\Access\AccessRights;
use OZONE\Core\Access\Interfaces\AccessRightsInterface;
use OZONE\Core\App\Settings;
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
	protected AccessRightsInterface $access_right;

	/**
	 * AuthorizationScope constructor.
	 */
	public function __construct()
	{
		$this->setTryMax((int) Settings::get('oz.auth', 'OZ_AUTH_CODE_TRY_MAX'))
			->setLifetime((int) Settings::get('oz.auth', 'OZ_AUTH_CODE_LIFE_TIME'));

		$this->access_right = new AccessRights();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getLabel(): string
	{
		return $this->label;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setLabel(string $label): static
	{
		$this->label = $label;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getLifetime(): int
	{
		return $this->lifetime;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setLifetime(int $lifetime): static
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
	#[Override]
	public function getTryMax(): int
	{
		return $this->try_max;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setTryMax(int $try_max): static
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
	#[Override]
	public function getAccessRight(): AccessRightsInterface
	{
		return $this->access_right;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setAccessRight(AccessRightsInterface $access_right): static
	{
		$this->access_right = $access_right;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function from(OZAuth $auth): static
	{
		$scope = new self();

		return $scope
			->setTryMax($auth->getTryMax())
			->setLifetime($auth->getLifetime())
			->setLabel($auth->getLabel())
			->setAccessRight(AccessRights::from($auth));
	}
}
