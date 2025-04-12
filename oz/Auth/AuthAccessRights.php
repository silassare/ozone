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
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class AuthAccessRights.
 */
class AuthAccessRights implements AuthAccessRightsInterface
{
	use ArrayCapableTrait;

	/**
	 * @var AuthAccessRightsInterface[]
	 */
	protected array $scopes = [];

	/**
	 * AuthAccessRights constructor.
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
	 * {@inheritDoc}
	 *
	 * @throws UnauthorizedActionException
	 */
	public function allow(string $action): self
	{
		if (!$this->allowedInScopes([$action])) {
			throw new UnauthorizedActionException('Access right escalation.', [
				'_action'  => $action,
				'_message' => 'Possible access rights escalation detected.',
			]);
		}

		$this->options[$action] = 1;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function deny(string $action): self
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
	public function can(string ...$actions): bool
	{
		if (!$this->allowedInScopes($actions)) {
			return false;
		}

		foreach ($actions as $action) {
			$is_wildcard = \str_contains($action, '*');
			// when it's not a wildcard check if the granular action is defined
			if (!$is_wildcard && isset($this->options[$action])) {
				$allowed = (bool) $this->options[$action];
			} else {
				// when it's a wildcard or the granular action is not defined
				$allowed = false;
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
						if (\str_starts_with($k, $parent) && !$v) {
							$allowed = false;

							break;
						}
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
		return new self((array) $auth->getPermissions());
	}

	/**
	 * {@inheritDoc}
	 */
	public function pushScope(AuthAccessRightsInterface $scope): AuthAccessRightsInterface
	{
		if (!\in_array($scope, $this->scopes, true)) {
			$this->scopes[] = $scope;
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return $this->options;
	}

	protected function allowedInScopes(array $actions): bool
	{
		foreach ($this->scopes as $scope) {
			if (!$scope->can(...$actions)) {
				return false;
			}
		}

		return true;
	}
}
