<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Authenticator;

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
		 * Gets token url for authentication
		 *
		 * @return array the token url info
		 * @throws \Exception
		 */
		public function getTokenUrl()
		{
			$auth      = $this->auth;
			$generated = $auth->generate()
							  ->getGenerated();
			$label     = $auth->getLabel();
			$token     = $generated['token'];
			$uri       = "$label/$token.auth";

			return ['tokenUrl' => $uri];
		}
	}