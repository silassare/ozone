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

namespace OZONE\Core\Access;

use Override;
use OZONE\Core\Access\Interfaces\AccessRightsInterface;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Exceptions\UnauthorizedException;
use OZONE\Core\Lang\I18nMessage;
use OZONE\Core\Roles\Enums\RoleCheckMode;
use OZONE\Core\Roles\Roles;
use OZONE\Core\Roles\RolesUtils;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class AccessRights.
 */
class AccessRights implements AccessRightsInterface
{
	use ArrayCapableTrait;

	/**
	 * @var AccessRightsInterface[]
	 */
	protected array $scopes = [];

	protected ?string $last_checked = null;

	/**
	 * AccessRights constructor.
	 */
	public function __construct(
		protected array $options = [],
		protected bool $auto_push_auth_user = true,
	) {
		if ($this->auto_push_auth_user) {
			$context = context();

			// this is to ensure we don't have access rights escalation
			if ($context->hasAuthenticatedUser()) {
				$au = $context->auth();
				if ($au->isScoped()) {
					$auth_user_rights = $au->getAccessRights();
					$this->pushScope($auth_user_rights);
				}
			}
		}
	}

	/**
	 * @throws UnauthorizedException
	 */
	#[Override]
	public function allow(string $action): static
	{
		if (!$this->allowedInScopes([$action])) {
			throw new UnauthorizedException('Access right escalation.', [
				'_action'  => $action,
				'_message' => 'Possible access rights escalation detected.',
			]);
		}

		$this->options[$action] = 1;

		return $this;
	}

	#[Override]
	public function deny(string $action): static
	{
		$this->options[$action] = 0;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Specific rules like `users.medias.upload` are more important than wildcard rules like `users.medias.*`,
	 * and users.medias.* is more specific than users.*
	 */
	#[Override]
	public function can(string ...$actions): bool
	{
		$this->last_checked = null;
		if (!$this->allowedInScopes($actions)) {
			return false;
		}

		foreach ($actions as $action) {
			$this->last_checked = $action;
			$is_wildcard        = \str_contains($action, '*');
			// when it's not a wildcard check if the granular action is defined
			if (!$is_wildcard && isset($this->options[$action])) {
				$allowed = (bool) $this->options[$action];
			} else {
				// when it's a wildcard or the granular action is not defined: `*` alone stands for every
				// action, then each more specific wildcard decides over it
				$allowed = (bool) ($this->options['*'] ?? false);
				$parts   = \explode('.', $action);

				// remove the last part
				\array_pop($parts);

				// for foo.bar.baz.tar,
				// will check for foo.*, foo.bar.*, foo.bar.baz.*
				// the last defined will be the result
				$parent = '';
				foreach ($parts as $part) {
					$parent .= $part . '.';
					$allowed = (bool) ($this->options[$parent . '*'] ?? $allowed);
				}

				// Ensure no specific denial exists for a child action
				// when allow: users.* and deny: users.delete
				// can: users.* ? no because users.delete is denied
				if ($is_wildcard) {
					foreach ($this->options as $k => $v) {
						if (!(\str_starts_with($k, $parent) && !$v)) {
							continue;
						}

						$allowed = false;

						break;
					}
				}
			}

			if (!$allowed) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @throws UnauthorizedException
	 */
	#[Override]
	public function assertCan(string ...$actions): void
	{
		if (!$this->can(...$actions)) {
			$last_checked = $this->last_checked;

			/** @var null|I18nMessage $error */
			$error = null;

			if ($last_checked) {
				$atomic_action = AtomicActionsRegistry::get($last_checked);
				$error         = $atomic_action?->getErrorMessage();
			}

			throw new UnauthorizedException($error, [
				'_actions'      => $actions,
				'_last_checked' => $last_checked,
			]);
		}
	}

	/**
	 * An auth user's rights, as every check reads them: their roles' rights joined
	 * ({@see RoleRights::of()}), then their own over them, so a right given or refused to the person
	 * wins over their roles. A super admin starts from every action (`*`). A scoped authentication
	 * (a key, a token) still caps them, as {@see self::__construct()} pushes its rights.
	 */
	public static function of(AuthUserInterface $user): self
	{
		// The role as the user holds it: a scoped authentication is capped by its own rights, below.
		$super = Roles::hasRole($user, RolesUtils::superAdmin(), true, RoleCheckMode::READ);
		$map   = $super ? ['*' => 1] : [];
		$map = \array_replace(
			$map,
			RoleRights::of($user),
			$user->getAuthUserDataStore()->getAuthUserAccessRights()->toArray()
		);

		return new self($map, false);
	}

	#[Override]
	public static function from(OZAuth $auth): static
	{
		return new self((array) $auth->getPermissions());
	}

	#[Override]
	public function pushScope(AccessRightsInterface $scope): static
	{
		if (!\in_array($scope, $this->scopes, true)) {
			$this->scopes[] = $scope;
		}

		return $this;
	}

	#[Override]
	public function toArray(): array
	{
		return $this->options;
	}

	protected function allowedInScopes(array $actions): bool
	{
		foreach ($this->scopes as $scope) {
			if ($scope->can(...$actions)) {
				continue;
			}

			if ($scope instanceof self) {
				$this->last_checked = $scope->last_checked;
			}

			return false;
		}

		return true;
	}
}
