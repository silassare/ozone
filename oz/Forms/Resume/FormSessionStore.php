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

namespace OZONE\Core\Forms\Resume;

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\FormResumeExpiredException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\FormDataClean;
use OZONE\Core\Forms\Resume\Interfaces\ResumableFormProviderInterface;
use OZONE\Core\Http\Enums\RequestScope;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Stores\Interfaces\StoreEntryExpiryListenerInterface;
use OZONE\Core\Stores\StateRegistry;
use OZONE\Core\Stores\StoresGarbageCollector;

/**
 * Class FormSessionStore.
 *
 * Persistence for resumable form sessions, backed by the `oz:form:sessions`
 * cache store, plus the entry points downstream code uses to consume a
 * completed session.
 */
final class FormSessionStore implements StoreEntryExpiryListenerInterface
{
	public const CACHE_NAMESPACE = 'oz:form:sessions';

	/**
	 * {@inheritDoc}
	 *
	 * Called by {@see StoresGarbageCollector} when a session
	 * expires without being cancelled or consumed. Delegates to the owning
	 * provider's {@see ResumableFormProviderInterface::onAbandon()} with the raw
	 * record. Records without a valid `provider_class` are ignored.
	 */
	#[Override]
	public static function onCacheEntryExpiry(string $key, mixed $value, string $store_name): void
	{
		if (!\is_array($value)) {
			return;
		}

		$class = $value['provider_class'] ?? null;

		if (!\is_string($class) || !\is_a($class, ResumableFormProviderInterface::class, true)) {
			return;
		}

		$class::onAbandon($value);
	}

	/**
	 * Loads a session, or returns null when it is absent, expired from the cache,
	 * or malformed.
	 */
	public static function load(string $resume_ref): ?FormSession
	{
		$cached = StateRegistry::store(self::CACHE_NAMESPACE)->get($resume_ref);

		if (!\is_array($cached)) {
			return null;
		}

		return FormSession::fromArray($resume_ref, $cached);
	}

	/**
	 * Writes (or overwrites) a session.
	 */
	public static function save(FormSession $session, int $ttl): void
	{
		StateRegistry::store(self::CACHE_NAMESPACE)->set($session->ref, $session->toArray(), $ttl);
	}

	/**
	 * Deletes a session.
	 *
	 * Call this after the route handler successfully consumes the session data to
	 * prevent reuse and free the cache entry immediately (rather than waiting for TTL).
	 *
	 * @param string $resume_ref the session reference to drop
	 */
	public static function drop(string $resume_ref): void
	{
		StateRegistry::store(self::CACHE_NAMESPACE)->delete($resume_ref);
	}

	/**
	 * Validates that a standalone session of the given provider belongs to the
	 * current caller and has been fully completed, then returns its data.
	 *
	 * For downstream handlers that consume a flow driven through the standalone
	 * `{group-path}/:provider/...` endpoints. The expected provider is required: the
	 * data of any other flow is rejected, as is a session bound to a route.
	 *
	 * The session is NOT deleted by this method; call {@see self::drop()} once the
	 * data has been consumed, or let the TTL handle cleanup.
	 *
	 * @param string                                       $resume_ref     the session reference returned by `init`
	 * @param Context                                      $context        current request context (ownership check)
	 * @param class-string<ResumableFormProviderInterface> $provider_class the provider whose flow must be complete
	 *
	 * @return FormDataClean accumulated validated data from all completed steps
	 *
	 * @throws NotFoundException          when the session does not exist or has expired
	 * @throws ForbiddenException         when not done, owned by another caller, or of another provider/route
	 * @throws FormResumeExpiredException when the session has passed its deadline
	 */
	public static function requireCompletion(
		string $resume_ref,
		Context $context,
		string $provider_class
	): FormDataClean {
		return self::complete($resume_ref, $context, $provider_class, null);
	}

	/**
	 * Route-side counterpart of {@see self::requireCompletion()}: the expected
	 * provider and route are those of the route being served.
	 *
	 * @throws NotFoundException          when the session does not exist or has expired
	 * @throws ForbiddenException         when not done, owned by another caller, or of another provider/route
	 * @throws FormResumeExpiredException when the session has passed its deadline
	 *
	 * @internal called by {@see RouteInfo::checkRouteForm()}
	 */
	public static function requireCompletionForRoute(string $resume_ref, RouteInfo $ri): FormDataClean
	{
		$provider_class = $ri->route()->getOptions()->resolveResumeProviderClass()
			?? throw new RuntimeException('The matched route has no resume support.');

		return self::complete($resume_ref, $ri->getContext(), $provider_class, $ri->route()->key());
	}

	/**
	 * @param class-string<ResumableFormProviderInterface> $provider_class
	 */
	private static function complete(
		string $resume_ref,
		Context $context,
		string $provider_class,
		?string $route_key
	): FormDataClean {
		$session = self::load($resume_ref);

		if (null === $session) {
			throw new NotFoundException('OZ_FORM_SESSION_NOT_FOUND', ['ref' => $resume_ref]);
		}

		// The scope was stored at init, so the provider never needs to be instantiated here.
		$scope_id = RequestScope::from($session->scope_name)->resolveId($context);

		if ($session->scope_id !== $scope_id) {
			throw new ForbiddenException('OZ_FORM_SESSION_ACCESS_DENIED');
		}

		$session->assertUsableBy($provider_class, $route_key);

		if ($session->isExpired()) {
			throw new FormResumeExpiredException();
		}

		if (!$session->isDone()) {
			throw new ForbiddenException('OZ_FORM_SESSION_NOT_DONE', ['ref' => $resume_ref]);
		}

		return $session->cleaned_fd;
	}
}
