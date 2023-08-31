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
use OZONE\Core\Auth\Interfaces\AuthScopeInterface;
use OZONE\Core\Db\OZAuth;

/**
 * Class AuthScope.
 */
class AuthScope extends AuthAccessRights implements AuthScopeInterface
{
	protected string $label    = '';
	protected int $try_max     = 0;
	protected int $lifetime    = 0;

	/**
	 * AuthScope constructor.
	 */
	public function __construct(array $options = [])
	{
		parent::__construct($options);

		$this->setTryMax((int) Settings::get('oz.auth', 'OZ_AUTH_CODE_TRY_MAX'))
			->setLifetime((int) Settings::get('oz.auth', 'OZ_AUTH_CODE_LIFE_TIME'));
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
	public static function from(OZAuth $auth): self
	{
		$scope = new self($auth->getOptions());

		return $scope->setTryMax($auth->getTryMax())
			->setLifetime($auth->getLifetime())
			->setLabel($auth->getLabel());
	}
}
