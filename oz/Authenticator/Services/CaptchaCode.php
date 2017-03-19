<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
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

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	/**
	 * Class CaptchaCode
	 * @package OZONE\OZ\Authenticator\Services
	 */
	final class CaptchaCode extends OZoneService {
		private static $REG_CAPTCHA_FILE_URI = "#^([a-z0-9]{32})\.png$#";

		/**
		 * CaptchaCode constructor.
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * {@inheritdoc}
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneNotFoundException
		 */
		public function execute( $request = array() ) {
			$extra_ok = OZoneUri::parseUriExtra( self::$REG_CAPTCHA_FILE_URI, array( 'key' ), $request );

			if ( !$extra_ok )
				throw new OZoneNotFoundException();

			OZoneAssert::assertForm( $request, array( 'key' ), new OZoneNotFoundException() );

			( new CaptchaCodeHelper() )->drawImage( $request[ 'key' ] );
		}
	}