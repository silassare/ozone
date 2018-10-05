<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\User\Views;

	use OZONE\OZ\Core\BaseView;
	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\Exceptions\BadRequestException;
	use OZONE\OZ\Authenticator\Services\AccountAuth;
	use OZONE\OZ\WebRoute\WebRoute;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class AccountAuthView
	 *
	 * @package OZONE\OZ\User\Views
	 */
	final class AccountAuthView extends BaseView
	{
		/**
		 * AccountAuthView constructor.
		 *
		 * @param array $request
		 *
		 * @throws \Exception
		 */
		public function __construct(array $request = [])
		{
			$enabled = SettingsManager::get("oz.authenticator", "OZ_AUTH_ACCOUNT_COOKIE_ENABLED");
			$aa_name = SettingsManager::get("oz.authenticator", "OZ_AUTH_ACCOUNT_COOKIE_NAME");

			if ($enabled === true AND isset($_COOKIE[$aa_name])) {
				$account = $_COOKIE[$aa_name];
			} elseif (isset($request["account"])) {
				$account = $request["account"];
			}

			if (empty($account)) {
				throw new BadRequestException();
			}

			$user = AccountAuth::check($account);

			if (isset($request['next'])) {
				$next = $request['next'];
				WebRoute::redirect($next);
			} else {
				echo "Active user -> {$user->getName()}";
				exit;
			}
		}

		/**
		 * Gets the view template compile data, called just before view rendering
		 *
		 * @return array
		 */
		public function getCompileData()
		{
			return [];
		}

		/**
		 * Gets the view template to render
		 *
		 * @return string
		 */
		public function getTemplate()
		{
			return '';
		}
	}