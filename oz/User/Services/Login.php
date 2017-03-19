<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\User\Services;

	use OZONE\OZ\Core\OZoneAssert;
	use OZONE\OZ\Core\OZoneService;
	use OZONE\OZ\Core\OZoneSessions;
	use OZONE\OZ\Ofv\OFormValidator;
	use OZONE\OZ\User\OZoneUserUtils;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	/**
	 * Class Login
	 * @package OZONE\OZ\User\Services
	 */
	final class Login extends OZoneService {

		/**
		 * Login constructor.
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * {@inheritdoc}
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneInvalidFormException
		 * @throws \OZONE\OZ\Exceptions\OZoneUnverifiedUserException
		 */
		public function execute( $request = array() ) {

			//et oui! user nous a soumit un formulaire alors on verifie que le formulaire est valide.
			OZoneUserUtils::logOut();

			OZoneAssert::assertForm( $request, array( 'phone', 'pass' ) );

			$fv_obj = new OFormValidator( $request );

			$fv_obj->checkForm( array(
				'phone' => array( 'registered' ),
				'pass'  => null
			) );

			$form = $fv_obj->getForm();
			$phone = $form[ 'phone' ];
			$pass = $form[ 'pass' ];

			$result = OZoneUserUtils::tryLogOnWith( 'phone', $phone, $pass );

			if ( $result ) {
				//on connecte user
				//et on lui renvois les infos sur lui
				self::$resp->setDone( 'OZ_USER_ONLINE' )->setData( $result );

			} else {
				//le mot de passe n'est pas correct
				self::$resp->setError( 'OZ_FIELD_PASS_INVALID' );
			}
		}
	}