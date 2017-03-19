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

	use OZONE\OZ\Authenticator\TokenUriHelper;
	use OZONE\OZ\Core\OZoneAssert;
	use OZONE\OZ\Core\OZoneService;
	use OZONE\OZ\Core\OZoneUri;
	use OZONE\OZ\Exceptions\OZoneNotFoundException;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	/**
	 * Class TokenUri
	 * @package OZONE\OZ\Authenticator\Services
	 */
	final class TokenUri extends OZoneService {
		private static $REG_TOKEN_URI = '#^([a-z0-9]{32})/([a-z0-9]{32})\.auth$#';

		/**
		 * ServiceTokenUri constructor.
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
			$extra_ok = OZoneUri::parseUriExtra( self::$REG_TOKEN_URI, array( 'label', 'token' ), $request );

			if ( !$extra_ok )
				throw new OZoneNotFoundException();

			OZoneAssert::assertForm( $request, array( 'label', 'token' ), new OZoneNotFoundException() );

			( new TokenUriHelper() )->validate( $request[ 'label' ], $request[ 'token' ] );
		}
	}