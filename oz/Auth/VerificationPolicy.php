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
use OZONE\Core\Auth\Enums\VerificationPurpose;
use OZONE\Core\Auth\Interfaces\AuthorizationProviderInterface;
use OZONE\Core\Auth\Providers\OwnershipVerificationProvider;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Exceptions\UnauthorizedException;
use OZONE\Core\Router\Guards\AuthorizationProviderRouteGuard;
use OZONE\Core\Router\RouteInfo;
use Throwable;

/**
 * What must be proven before an account is created, or handed back.
 *
 * The rules live in `oz.auth.verification`, per user type: which authorization providers are accepted
 * (`auth:provider:email:verify`, a project's own, ...), or none at all when the identifier is taken as
 * given and verified later.
 *
 * A route guard cannot hold these rules: it runs before the handler, while the user type is a field of
 * the form. So a route is guarded only when **every** type requires a verification, and the handler
 * always checks the rule of the type it was actually given, running that check itself when the route
 * could not.
 */
final class VerificationPolicy
{
	/** The entry a user type with no rule of its own falls back to. */
	public const string ANY_USER_TYPE = '*';

	/** The settings key of what `POST /signup` accepts. */
	public const string SIGN_UP = 'OZ_SIGNUP_VERIFICATION';

	/** The settings key of what `POST /account-recovery` accepts. */
	public const string ACCOUNT_RECOVERY = 'OZ_ACCOUNT_RECOVERY_VERIFICATION';

	/** The purpose an email or phone verification must have been started for, by settings key. */
	private const array PURPOSES = [
		self::SIGN_UP          => VerificationPurpose::SIGN_UP,
		self::ACCOUNT_RECOVERY => VerificationPurpose::ACCOUNT_RECOVERY,
	];

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
	 * @throws ForbiddenException    when the type must prove something and this request does not
	 * @throws NotFoundException     when the authorization presented does not exist
	 * @throws UnauthorizedException when the authorization presented is not granted
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

		// The route demanded an authorization at its door only when every user type has to present
		// one; otherwise the one this type must present is checked here, as the guard does.
		$results = $ri->hasGuardStoredResults(AuthorizationProviderRouteGuard::class)
			? AuthorizationProviderRouteGuard::resolveResults($ri)
			: new AuthorizationProviderRouteGuard($allowed)->check($ri);

		$provider = $results['provider'];

		if (!\in_array($provider::getName(), $allowed, true)) {
			throw new ForbiddenException(null, [
				// The allowed list is not revealed: it would say which door to try next.
				'_reason'    => 'Auth provider is not allowed for this user type.',
				'_user_type' => $user_type,
			]);
		}

		// An email or phone verification says what it is for, and its owner confirmed that.
		// A key a project added names no purpose of OZone's: the project checks it.
		$purpose = self::PURPOSES[$key] ?? null;

		if ($provider instanceof OwnershipVerificationProvider && null !== $purpose) {
			if ($purpose !== $provider->getPurpose()) {
				throw new ForbiddenException('OZ_AUTH_WRONG_PURPOSE', [
					'_purpose'  => $provider->getPurpose()->value,
					'_expected' => $purpose->value,
				]);
			}

			$for_type = $provider->getUserType();

			if (null !== $for_type && $for_type !== $user_type) {
				throw new ForbiddenException('OZ_AUTH_WRONG_PURPOSE', [
					'_reason'    => 'The verification is for another user type.',
					'_user_type' => $user_type,
					'_for_type'  => $for_type,
				]);
			}
		}

		return $provider;
	}

	/**
	 * Spends the authorization an action was allowed by, once the action is done: it proved what it
	 * had to, and must not allow the action a second time.
	 *
	 * @param AuthorizationProviderInterface $provider what {@see self::resolve()} gave
	 */
	public static function consume(AuthorizationProviderInterface $provider): void
	{
		$auth = Auth::get($provider->getCredentials()->getReference());

		try {
			$auth?->selfDelete();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to delete the authorization an action used.', null, $t);
		}
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
