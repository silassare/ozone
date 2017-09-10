<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Authenticator\Services;

	use OZONE\OZ\Authenticator\CaptchaCodeHelper;
	use OZONE\OZ\Core\OZoneAssert;
	use OZONE\OZ\Core\OZoneService;
	use OZONE\OZ\Core\OZoneUri;
	use OZONE\OZ\Exceptions\OZoneNotFoundException;
	use OZONE\OZ\Core\OZoneSettings;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class CaptchaCode
	 *
	 * @package OZONE\OZ\Authenticator\Services
	 */
	final class CaptchaCode extends OZoneService
	{

		/**
		 * CaptchaCode constructor.
		 */
		public function __construct()
		{
			parent::__construct();
		}

		/**
		 * {@inheritdoc}
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneNotFoundException
		 */
		public function execute($request = [])
		{
			$params = ['key'];

			$file_uri_reg = OZoneSettings::get('oz.files', "OZ_CAPTCHA_FILE_REG");
			$extra_ok     = OZoneUri::parseUriExtra($file_uri_reg, $params, $request);

			if (!$extra_ok) {
				throw new OZoneNotFoundException();
			}

			OZoneAssert::assertForm($request, $params, new OZoneNotFoundException());

			CaptchaCodeHelper::serveCaptchaImage($request['key']);
		}
	}