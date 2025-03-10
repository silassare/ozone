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

/**
 * Class AuthAccessRights.
 */
class AuthAccessRights implements AuthAccessRightsInterface
{
	/**
	 * AuthAccessRights constructor.
	 */
	public function __construct(protected array $options = []) {}

	/**
	 * {@inheritDoc}
	 */
	public function allow(string $action): self
	{
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
		return new self((array) $auth->getOptions());
	}

	/**
	 * {@inheritDoc}
	 */
	public function getOptions(): array
	{
		return $this->options;
	}
}
