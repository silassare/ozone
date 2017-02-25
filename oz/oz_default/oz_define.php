<?php
	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	define( 'OZ_OZONE_VERSION', '1.0.0' );
	define( 'OZ_OZONE_VERSION_NAME', 'OZone v' . OZ_OZONE_VERSION );

	define( 'OZ_OZONE_DIR', OZ_ROOT_DIR . 'oz' . DS );
	define( 'OZ_OZONE_SETTINGS_DIR', OZ_OZONE_DIR . 'oz_settings' . DS );
	define( 'OZ_OZONE_ASSETS_DIR', OZ_OZONE_DIR . 'oz_assets' . DS );

	define( 'OZ_APP_CORE_DIR', OZ_APP_DIR . 'oz_core' . DS );
	define( 'OZ_APP_SERVICES_DIR', OZ_APP_DIR . 'oz_services' . DS );
	define( 'OZ_APP_SETTINGS_DIR', OZ_APP_DIR . 'oz_settings' . DS );
	define( 'OZ_APP_TEMPLATES_DIR', OZ_APP_DIR . 'oz_templates' . DS );
	define( 'OZ_APP_USERS_FILES_DIR', OZ_APP_DIR . 'oz_userfiles' . DS );