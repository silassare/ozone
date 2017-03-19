<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\User;

	use OZONE\OZ\Core\OZoneDb;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	/**
	 * Class OZoneUser the default user object class
	 * @package OZONE\OZ\User
	 */
	class OZoneUser extends OZoneUserBase {
		/**
		 * OZoneUser constructor.
		 *
		 * @param int|string $uid
		 */
		function __construct( $uid ) {
			parent::__construct( $uid );
		}

		/**
		 * {@inheritdoc}
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneInternalError
		 */

		public function userDataFilter( array $user_data ) {

			return OZoneDb::mapDbFieldsToExtern( $user_data, array(
				'user_id',
				'user_name',
				'user_phone',
				'user_email',
				'user_cc2',
				'user_sex',
				'user_bdate',
				'user_picid'
			) );
		}
	}
