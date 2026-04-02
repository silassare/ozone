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

use Override;
use OZONE\Core\CSRF\CSRF;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Http\Enums\RequestScope;
use OZONE\Core\Router\RouteInfo;

/**
 * Class CSRFRouteGuard.
 */
final class CSRFRouteGuard extends AbstractRouteGuard
{
	/**
	 * CSRFRouteGuard constructor.
	 */
	public function __construct(
		private RequestScope $scope
	) {}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function toRules(): array
	{
		return [
			'scope'  => $this->scope->value,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function fromRules(array $rules): static
	{
		$scope = RequestScope::tryFrom($rules['scope'] ?? '') ?? RequestScope::STATE;

		return new self($scope);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 */
	#[Override]
	public function check(RouteInfo $ri): bool
	{
		$context  = $ri->getContext();
		$csrf     = new CSRF($context, $this->scope);

		if ($csrf->check($ri)) {
			return true;
		}

		throw new ForbiddenException(null, [
			// we use '_reason' instead of 'reason' because
			// we want only developers to know the real reason
			'_reason' => 'invalid csrf token',
		]);
	}
}
