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

use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Forms\FormDataClean;
use OZONE\Core\Forms\Resume\Enums\FormResumePhase;
use OZONE\Core\Forms\Resume\Interfaces\ResumableFormProviderInterface;
use OZONE\Core\Router\Route;

/**
 * Class FormSession.
 *
 * Typed view of a resumable form session record.
 *
 * The cache holds the plain array produced by {@see self::toArray()}. Its keys are
 * unchanged except for the optional `route` binding, so standalone sessions keep
 * the exact previous shape and {@see ResumableFormProviderInterface::onAbandon()}
 * keeps receiving it.
 *
 * @internal
 */
final class FormSession
{
	/**
	 * FormSession constructor.
	 *
	 * @param string                                       $ref            the session reference (cache key)
	 * @param class-string<ResumableFormProviderInterface> $provider_class the provider that owns the session
	 * @param null|string                                  $provider_name  the registry name, set only for
	 *                                                                     sessions opened through the
	 *                                                                     standalone endpoints
	 * @param null|string                                  $route          the {@see Route::key()} of the route
	 *                                                                     the session was opened on; null for
	 *                                                                     standalone sessions
	 * @param string                                       $scope_name     the RequestScope value
	 * @param string                                       $scope_id       the resolved scope ID of the owner
	 * @param int                                          $created_at     creation UNIX timestamp
	 * @param null|int                                     $expires_at     hard deadline UNIX timestamp, or null
	 * @param FormResumePhase                              $phase          current phase
	 * @param FormDataClean                                $cleaned_fd     validated data accumulated so far
	 * @param FormResumeProgress                           $progress       provider progress state
	 * @param list<array>                                  $history        snapshots for going back, each
	 *                                                                     `{cleaned_fd, progress_state}`
	 */
	public function __construct(
		public readonly string $ref,
		public readonly string $provider_class,
		public readonly ?string $provider_name,
		public readonly ?string $route,
		public readonly string $scope_name,
		public readonly string $scope_id,
		public readonly int $created_at,
		public readonly ?int $expires_at,
		public FormResumePhase $phase,
		public FormDataClean $cleaned_fd,
		public FormResumeProgress $progress,
		public array $history = [],
	) {}

	/**
	 * Rebuilds a session from its cached array, or returns null when the record
	 * is missing a required key or holds an unknown phase.
	 *
	 * @param string $ref  the session reference (cache key)
	 * @param array  $data the cached record
	 *
	 * @return null|self
	 */
	public static function fromArray(string $ref, array $data): ?self
	{
		$provider_class = $data['provider_class'] ?? null;
		$phase          = FormResumePhase::tryFrom((string) ($data['phase'] ?? ''));

		if (
			null === $phase
			|| !\is_string($provider_class)
			|| !\is_a($provider_class, ResumableFormProviderInterface::class, true)
			|| !isset($data['scope_name'], $data['scope_id'])
		) {
			return null;
		}

		return new self(
			$ref,
			$provider_class,
			isset($data['provider_name']) ? (string) $data['provider_name'] : null,
			isset($data['route']) ? (string) $data['route'] : null,
			(string) $data['scope_name'],
			(string) $data['scope_id'],
			(int) ($data['created_at'] ?? 0),
			isset($data['expires_at']) ? (int) $data['expires_at'] : null,
			$phase,
			new FormDataClean($data['cleaned_fd'] ?? []),
			new FormResumeProgress($data['progress_state'] ?? []),
			$data['history'] ?? [],
		);
	}

	/**
	 * Checks that this session may be driven or consumed by the given provider on
	 * the given route.
	 *
	 * - The provider must be the one the session was opened with, otherwise the
	 *   data of one flow could stand in for another flow's validated input.
	 * - A route-bound session is only usable on that same route: every
	 *   `->resumable()` route shares {@see ResumableFormProvider}, so the
	 *   provider alone cannot tell those routes apart.
	 * - A standalone session is usable wherever its provider is, unless that
	 *   provider requires real context: such a provider never opens standalone
	 *   sessions, so an unbound one is not trusted.
	 *
	 * @param class-string<ResumableFormProviderInterface> $provider_class the provider expected by the caller
	 * @param null|string                                  $route_key      {@see Route::key()} of the route being
	 *                                                                     served, or null outside a route
	 *
	 * @throws ForbiddenException when the session belongs to another provider or route
	 */
	public function assertUsableBy(string $provider_class, ?string $route_key): void
	{
		if ($this->provider_class !== $provider_class) {
			throw new ForbiddenException('OZ_FORM_SESSION_PROVIDER_MISMATCH');
		}

		if (null === $this->route) {
			if ($provider_class::requiresRealContext()) {
				throw new ForbiddenException('OZ_FORM_SESSION_ROUTE_MISMATCH');
			}

			return;
		}

		if ($this->route !== $route_key) {
			throw new ForbiddenException('OZ_FORM_SESSION_ROUTE_MISMATCH');
		}
	}

	/**
	 * Whether the hard deadline has passed.
	 */
	public function isExpired(): bool
	{
		return null !== $this->expires_at && \time() > $this->expires_at;
	}

	/**
	 * Whether every step has been submitted.
	 */
	public function isDone(): bool
	{
		return FormResumePhase::DONE === $this->phase;
	}

	/**
	 * The cached array shape.
	 *
	 * `provider_name` is only present for standalone sessions and `route` only for
	 * route-bound ones.
	 *
	 * @return array
	 */
	public function toArray(): array
	{
		$data = [
			'provider_class' => $this->provider_class,
			'phase'          => $this->phase->value,
			'cleaned_fd'     => $this->cleaned_fd->toArray(),
			'progress_state' => $this->progress->toArray(),
			'scope_id'       => $this->scope_id,
			'scope_name'     => $this->scope_name,
			'created_at'     => $this->created_at,
			'expires_at'     => $this->expires_at,
			'history'        => $this->history,
		];

		if (null !== $this->provider_name) {
			$data['provider_name'] = $this->provider_name;
		}

		if (null !== $this->route) {
			$data['route'] = $this->route;
		}

		return $data;
	}
}
