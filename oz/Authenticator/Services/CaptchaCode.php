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
	use OZONE\OZ\Core\Assert;
	use OZONE\OZ\Core\BaseService;
	use OZONE\OZ\Core\URIHelper;
	use OZONE\OZ\Exceptions\NotFoundException;
	use OZONE\OZ\Core\SettingsManager;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class CaptchaCode
	 *
	 * @package OZONE\OZ\Authenticator\Services
	 */
	final class CaptchaCode extends BaseService
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
		 * @throws \OZONE\OZ\Exceptions\NotFoundException
		 */
		public function execute(array $request = [])
		{
			$params = ['key'];

			$file_uri_reg = SettingsManager::get('oz.files', "OZ_CAPTCHA_FILE_REG");
			$extra_ok     = URIHelper::parseUriExtra($file_uri_reg, $params, $request);

			if (!$extra_ok) {
				throw new NotFoundException();
			}

			Assert::assertForm($request, $params, new NotFoundException());

			CaptchaCodeHelper::serveCaptchaImage($request['key']);
		}
	}