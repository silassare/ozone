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

use OZONE\Core\Forms\Form;
use OZONE\Core\Http\Response;
use OZONE\Core\Web\WebView;

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
		return $this->setTemplate('oz.route.access.grant.form.blate')
			->inject([
				'form' => $form,
			])
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
}
