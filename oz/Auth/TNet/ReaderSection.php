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

namespace OZONE\Core\Auth\TNet;

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\Interfaces\TNetSectionInterface;
use OZONE\Core\Roles\Interfaces\RoleInterface;
use OZONE\Core\Roles\RolesUtils;

/**
 * Class ReaderSection.
 *
 * Who is reading, as a client needs it to decide what to draw: the identity type and id, the
 * roles, and the weights of the project's role enum. `_current_user` is the user's row, which
 * carries neither the type (a method, not a column) nor the roles (a private table), so a client
 * could otherwise not tell a user from a client with the same id, nor which roles a reader holds.
 *
 * What it answers is what the server **decides with**, not what is stored: under a scoped auth
 * the roles grant nothing ({@see \OZONE\Core\Roles\Enums\RoleCheckMode::GRANT_ACCESS}), so they
 * are sent empty there, and a client draws what the server would allow rather than what the
 * account could do outside this scope.
 *
 * The weights are the whole hierarchy of the project's enum, sent to every signed-in reader so a
 * client can decide "this role or above" without a round trip. That makes the hierarchy visible to
 * a signed-in user; a project for which it must not be replaces this section in
 * `oz.tnet.sections`.
 */
final class ReaderSection implements TNetSectionInterface
{
	public const string NAME = 'reader';

	#[Override]
	public static function requiresAuthenticatedUser(): bool
	{
		return true;
	}

	#[Override]
	public function answer(Context $context): array
	{
		$auth = $context->auth();

		return self::of($auth->user(), $auth->isScoped());
	}

	/**
	 * A role as the wire carries it: its name, as text.
	 *
	 * A project's enum may be backed by integers, and a role is a name to a client whatever backs
	 * it -- so the one conversion is here, typed on the interface every project's enum implements.
	 */
	private static function nameOf(RoleInterface $role): string
	{
		return (string) $role->value;
	}

	/**
	 * The section for a user, apart from the request that asks for it.
	 *
	 * @return array{type: string, id: string, roles: list<string>, weights: array<string, int>}
	 */
	public static function of(AuthUserInterface $user, bool $scoped): array
	{
		$roles = [];

		if (!$scoped) {
			foreach (RolesUtils::roles($user) as $entry) {
				$roles[self::nameOf($entry->getRole())] = true;
			}
		}

		$weights = [];

		foreach (RolesUtils::getRoleEnumClass()::cases() as $role) {
			$weights[self::nameOf($role)] = $role->weight();
		}

		return [
			'type'    => $user->getAuthUserType(),
			'id'      => $user->getAuthIdentifier(),
			'roles'   => \array_map('strval', \array_keys($roles)),
			'weights' => $weights,
		];
	}
}
