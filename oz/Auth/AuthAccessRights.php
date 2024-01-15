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

use OZONE\Core\Auth\Interfaces\AuthAccessRightsInterface;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Exceptions\UnauthorizedActionException;
use PHPUtils\Store\Store;

/**
 * Class AuthAccessRights.
 */
class AuthAccessRights implements AuthAccessRightsInterface
{
	/**
	 * @var \PHPUtils\Store\Store<array>
	 */
	protected Store $store;

	/**
	 * AuthAccessRights constructor.
	 */
	public function __construct(array $options = [])
	{
		$this->store = new Store($options);
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
	 * @throws UnauthorizedActionException
	 */
	public function assertCan(string ...$actions): void
	{
		if (!$this->can(...$actions)) {
			throw new UnauthorizedActionException(null, [
				'_actions' => $actions,
			]);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public static function from(OZAuth $auth): static
	{
		return new self($auth->getOptions());
	}

	/**
	 * {@inheritDoc}
	 */
	public function getOptions(): array
	{
		return $this->store->getData();
	}
}
