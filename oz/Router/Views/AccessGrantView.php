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

namespace OZONE\OZ\Router\Views;

use OZONE\OZ\Forms\Form;
use OZONE\OZ\Http\Response;
use OZONE\OZ\Web\WebView;

/**
 * Class AccessGrantView.
 */
final class AccessGrantView extends WebView
{
	/**
	 * @param \OZONE\OZ\Forms\Form $form
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function renderAccessGrantForm(Form $form): Response
	{
		return $this->setTemplate('route.access.grant.form.otpl')
			->inject([
				'form' => $form,
			])
			->respond();
	}

	/**
	 * @param string $realm
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function renderAccessGrantAuth(string $realm): Response
	{
		return $this->setTemplate('route.access.grant.auth.otpl')
			->inject([
				'realm' => $realm,
			])
			->respond();
	}
}
