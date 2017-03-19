<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	define( 'OZ_OZONE_VERSION', '2.0.0' );
	define( 'OZ_OZONE_VERSION_NAME', 'OZone v' . OZ_OZONE_VERSION );

	define( 'OZ_OZONE_DIR', OZ_ROOT_DIR . 'oz' . DS );
	define( 'OZ_OZONE_SETTINGS_DIR', OZ_OZONE_DIR . 'oz_settings' . DS );
	define( 'OZ_OZONE_ASSETS_DIR', OZ_OZONE_DIR . 'oz_assets' . DS );

	define( 'OZ_APP_SETTINGS_DIR', OZ_APP_DIR . 'oz_settings' . DS );
	define( 'OZ_APP_TEMPLATES_DIR', OZ_APP_DIR . 'oz_templates' . DS );
	define( 'OZ_APP_USERS_FILES_DIR', OZ_APP_DIR . 'oz_userfiles' . DS );