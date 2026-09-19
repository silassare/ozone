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

use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Interfaces\AuthorizationProviderInterface;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Router\Guards\AuthorizationProviderRouteGuard;
use OZONE\Core\Router\RouteInfo;

/**
 * What must be proven before an account is created, or handed back.
 *
 * The rules live in `oz.auth.verification`, per user type: which authorization providers are accepted
 * (`auth:provider:email:verify`, a project's own, ...), or none at all when the identifier is taken as
 * given and verified later.
 *
 * A route guard cannot hold these rules: it runs before the handler, while the user type is a field of
 * the form. So a route is guarded only when **every** type requires a verification, and the handler
 * always checks the rule of the type it was actually given.
 */
final class VerificationPolicy
{
	/** The entry a user type with no rule of its own falls back to. */
	public const ANY_USER_TYPE = '*';

	/** The settings key of what `POST /signup` accepts. */
	public const SIGN_UP = 'OZ_SIGNUP_VERIFICATION';

	/** The settings key of what `POST /account-recovery` accepts. */
	public const ACCOUNT_RECOVERY = 'OZ_ACCOUNT_RECOVERY_VERIFICATION';

	/**
	 * The providers a given user type may prove itself with.
	 *
	 * @param string $key       one of the settings keys of this class
	 * @param string $user_type the user type, as `oz.auth.users.repositories` names it
	 *
	 * @return list<string> the provider names, empty when nothing has to be proven
	 */
	public static function providersFor(string $key, string $user_type): array
	{
		$rules = self::rules($key);

		$providers = $rules[$user_type] ?? $rules[self::ANY_USER_TYPE] ?? [];

		return \array_values($providers);
	}

	/**
	 * Every provider named anywhere in a rule, which is what a route may accept at its door.
	 *
	 * @return list<string>
	 */
	public static function allProviders(string $key): array
	{
		$names = [];

		foreach (self::rules($key) as $providers) {
			foreach ($providers as $provider) {
				$names[$provider] = true;
			}
		}

		return \array_keys($names);
	}

	/**
	 * Whether every user type has something to prove, so the route itself can demand it.
	 *
	 * A single type that requires nothing makes this false: the route stays open and the handler
	 * refuses what its own rule refuses.
	 */
	public static function alwaysRequired(string $key): bool
	{
		$rules = self::rules($key);

		if (empty($rules)) {
			return false;
		}

		foreach ($rules as $providers) {
			if (empty($providers)) {
				return false;
			}
		}

		return isset($rules[self::ANY_USER_TYPE]);
	}

	/**
	 * The authorization a request carries, once checked against the rule of its user type.
	 *
	 * @param RouteInfo $ri        the request
	 * @param string    $key       one of the settings keys of this class
	 * @param string    $user_type the user type the request is for
	 *
	 * @return null|AuthorizationProviderInterface null when that type has nothing to prove
	 *
	 * @throws ForbiddenException when the type must prove something and this request does not
	 */
	public static function resolve(
		RouteInfo $ri,
		string $key,
		string $user_type
	): ?AuthorizationProviderInterface {
		$allowed = self::providersFor($key, $user_type);

		if (empty($allowed)) {
			return null;
		}

		$results = $ri->hasGuardStoredResults(AuthorizationProviderRouteGuard::class)
			? AuthorizationProviderRouteGuard::resolveResults($ri)
			: null;

		$provider = $results['provider'] ?? null;

		if (null === $provider) {
			throw new ForbiddenException('OZ_AUTH_REF_NOT_PROVIDED', [
				'_reason'    => 'This user type requires a verified authorization.',
				'_user_type' => $user_type,
			]);
		}

		if (!\in_array($provider::getName(), $allowed, true)) {
			throw new ForbiddenException(null, [
				// The allowed list is not revealed: it would say which door to try next.
				'_reason'    => 'Auth provider is not allowed for this user type.',
				'_user_type' => $user_type,
			]);
		}

		return $provider;
	}

	/**
	 * @return array<string, list<string>>
	 */
	private static function rules(string $key): array
	{
		/** @var array<string, list<string>> $rules */
		return Settings::get('oz.auth.verification', $key, []);
	}
}
