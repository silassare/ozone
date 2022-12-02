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

namespace OZONE\OZ\Auth;

use InvalidArgumentException;
use OZONE\OZ\Auth\Interfaces\AuthScopeInterface;
use OZONE\OZ\Core\Configs;
use OZONE\OZ\Db\OZAuth;
use OZONE\OZ\Exceptions\UnauthorizedActionException;
use PHPUtils\Store\Store;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class AuthScope.
 */
class AuthScope implements AuthScopeInterface
{
	use ArrayCapableTrait;

	protected string $label    = '';
	protected int    $try_max  = 0;
	protected int    $lifetime = 0;
	/**
	 * @var \PHPUtils\Store\Store<array>
	 */
	protected Store  $store;

	public function __construct(protected string $value = '')
	{
		$this->store = new Store([]);

		$this->setTryMax((int) Configs::get('oz.auth', 'OZ_AUTH_CODE_TRY_MAX'))
			->setLifetime((int) Configs::get('oz.auth', 'OZ_AUTH_CODE_LIFE_TIME'));
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
	public function getValue(): string
	{
		return $this->value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setValue(string $value): self
	{
		$this->value = $value;

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
			throw new InvalidArgumentException(\sprintf('lifetime=%s should be a positive integer greater than 0.', $lifetime));
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
			throw new InvalidArgumentException(\sprintf('try_max=%s should be a positive integer greater than 0.', $try_max));
		}

		$this->try_max = $try_max;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function allow(string $action): self
	{
		$this->store->set($action, 1);

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function deny(string $action): self
	{
		$this->store->set($action, 0);

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function can(string ...$actions): bool
	{
		foreach ($actions as $action) {
			if (!$this->store->has($action)) {
				$parts       = \explode('.', $action);
				$full_access = false;
				$tree        = null;

				foreach ($parts as $k) {
					$tree = $tree ? $tree . '.' . $k : $k;

					if (true === (bool) $this->store->get($tree . '.*')) {
						$full_access = true;

						break;
					}
				}

				if (!$full_access) {
					return false;
				}
			} elseif (true !== (bool) $this->store->get($action)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
	 */
	public function assertCan(string ...$actions): void
	{
		if (!$this->can(...$actions)) {
			throw new UnauthorizedActionException();
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public static function from(OZAuth $auth): self
	{
		$scope = new self();

		$scope->store = new Store($auth->getData());

		return $scope->setValue($auth->getFor())
			->setTryMax($auth->getTryMax())
			->setLifetime($auth->getLifetime());
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return $this->store->getData();
	}
}
