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

use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Users\Users;

/**
 * Class UserRoleRouteGuard.
 */
class UserRoleRouteGuard extends AbstractRouteGuard
{
	private FormData $form_data;
	private bool $strict = true;

	/**
	 * @var array<string, 1>
	 */
	private array $roles;

	/**
	 * UserRoleRouteGuard constructor.
	 *
	 * @param string ...$roles
	 */
	public function __construct(string ...$roles)
	{
		foreach ($roles as $role) {
			$this->roles[$role] = 1;
		}

		$this->form_data = new FormData();
	}

	/**
	 * Should it be strict?
	 *
	 * @param bool $strict
	 *
	 * @return $this
	 */
	public function strict(bool $strict = true): self
	{
		$this->strict = $strict;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getRules(): array
	{
		return [
			'roles'  => \array_keys($this->roles),
			'strict' => $this->strict,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public static function fromRules(array $rules): self
	{
		$g = new self(...$rules['roles']);
		$s = $rules['strict'] ?? true;

		return $g->strict($s);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \OZONE\Core\Exceptions\ForbiddenException
	 */
	public function checkAccess(RouteInfo $ri): void
	{
		$context = $ri->getContext();
		$uid     = $context->user()
			->getID();

		$roles = \array_keys($this->roles);

		if (Users::hasOneRoleAtLeast($uid, $roles, $this->strict)) {
			throw new ForbiddenException(null, [
				'_reason' => 'User role is not in allowed list.',
			]);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function getFormData(): FormData
	{
		return $this->form_data;
	}
}
