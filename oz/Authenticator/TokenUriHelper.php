<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Authenticator;

	use OZONE\OZ\Core\URIHelper;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class TokenUriHelper
	 *
	 * @package OZONE\OZ\Authenticator
	 */
	final class TokenUriHelper implements AuthenticatorHelper
	{

		private $auth = null;

		/**
		 * TokenUriHelper constructor.
		 *
		 * @param \OZONE\OZ\Authenticator\Authenticator $auth
		 */
		public function __construct(Authenticator $auth)
		{
			$this->auth = $auth;
		}

		/**
		 * Gets token url info for authentication
		 *
		 * @return array The token url info
		 * @throws \Exception
		 */
		public function getTokenInfo()
		{
			$auth      = $this->auth;
			$generated = $auth->generate()
							  ->getGenerated();

			return [
				"auth_label"     => $generated['auth_label'],
				"auth_expire"    => $generated['auth_expire'],
				"auth_token"     => $generated['auth_token'],
				"auth_for_value" => $generated['auth_for_value']
			];
		}
	}