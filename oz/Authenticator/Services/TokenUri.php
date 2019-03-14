<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Authenticator\Services;

	use OZONE\OZ\Authenticator\Authenticator;
	use OZONE\OZ\Core\Assert;
	use OZONE\OZ\Core\BaseService;
	use OZONE\OZ\Core\URIHelper;
	use OZONE\OZ\Exceptions\NotFoundException;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class TokenUri
	 *
	 * @package OZONE\OZ\Authenticator\Services
	 */
	final class TokenUri extends BaseService
	{
		private static $REG_TOKEN_URI = '#^([a-z0-9]{32})/([a-z0-9]{32})\.auth$#';

		/**
		 * TokenUri constructor.
		 */
		public function __construct()
		{
			parent::__construct();
		}

		/**
		 * {@inheritdoc}
		 *
		 * @param array $request
		 *
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 * @throws \OZONE\OZ\Exceptions\NotFoundException
		 * @throws \Exception
		 */
		public function execute(array $request = [])
		{
			$out      = [];
			$extra_ok = URIHelper::parseUriExtra(self::$REG_TOKEN_URI, ['label', 'token'], $out);

			if (!$extra_ok) throw new NotFoundException();

			Assert::assertForm($out, ['label', 'token'], new NotFoundException());

			$name       = "mail_validator";
			$email      = "sample@mail.com";
			$auth_obj   = new Authenticator($name, $email);
			$auth_label = $out['label'];
			$auth_token = $out['token'];
			$valid      = false;

			if ($auth_obj->canUseLabel($auth_label)) {
				$auth_obj->setLabel($auth_label);
				$valid = $auth_obj->validateToken($auth_token);
			}

			if ($valid) {
				// do something good here or inform, redirect user
			} else {
				throw new NotFoundException();
			}
		}
	}