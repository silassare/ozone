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

namespace OZONE\Core\Router\Views;

use OZONE\Core\CSRF\CSRF;
use OZONE\Core\Forms\Form;
use OZONE\Core\Http\Enums\RequestScope;
use OZONE\Core\Http\Response;
use OZONE\Core\Web\WebView;
use Throwable;

/**
 * Class AccessGrantView.
 */
final class AccessGrantView extends WebView
{
	/**
	 * @param Form $form
	 *
	 * @return Response
	 */
	public function renderAccessGrantForm(Form $form): Response
	{
		/** @var array<string, mixed> $data a generic map here, not the form's declared shape */
		$data = $form->toArray();

		// The form posts back to the protected route: it must carry a CSRF token when one applies.
		$data['_csrf'] ??= $this->csrfToken();

		return $this->setTemplate('oz.route.access.grant.form.blate')
			->inject(['form' => $data])
			->respond();
	}

	/**
	 * Render the access grant form with a custom template.
	 *
	 * @param array $info
	 *
	 * @return Response
	 */
	public function renderAccessGrantAuth(array $info): Response
	{
		return $this->setTemplate('oz.route.access.grant.auth.blate')
			->inject($info)
			->respond();
	}

	/**
	 * A token for the route's CSRF scope (the session by default), or null when there is no
	 * scope to bind one to (e.g. no stateful auth), in which case no check applies either.
	 */
	private function csrfToken(): ?string
	{
		$context = $this->getContext();

		try {
			$scope = $context->getRouteInfo()->route()->getOptions()->getCSRFScope() ?? RequestScope::STATE;

			return (new CSRF($context, $scope))->generateToken();
		} catch (Throwable) {
			return null;
		}
	}
}
