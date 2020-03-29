<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Authenticator;

use OZONE\OZ\Core\Context;

\defined('OZ_SELF_SECURITY_CHECK') || die;

/**
 * Class QRCodeHelper
 */
final class LinkHelper
{
	/**
	 * Gets link for authentication
	 *
	 * @param \OZONE\OZ\Core\Context                $context
	 * @param \OZONE\OZ\Authenticator\Authenticator $auth
	 *
	 * @throws \Exception
	 *
	 * @return \OZONE\OZ\Http\Uri
	 */
	public static function getUrl(Context $context, Authenticator $auth)
	{
		$generated   = $auth->generate(1)
							->getGenerated();

		$data = $generated['auth_token'];

		return $context->buildRouteUri('oz:auth-link', [
			'token' => $token,
		]);
	}
}
