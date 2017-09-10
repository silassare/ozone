<?php
	/*
	 * OTpl php-1.1.4
	 * Auto-generated file, please don't edit
	 *
	 */

	if (
	OTpl::register([
		'version'      => "php-1.1.4",
		'version_name' => "OTpl php-1.1.4",
		'src_path'     => "/mnt/Emile/silo/ozone/oz/oz_templates/gen/index.api.otpl",
		'compile_time' => 1504974759,
		'func_name'    => "otpl_func_c461af7a124b7d53bae822affbc0888b"
	])
	) {
		function otpl_func_c461af7a124b7d53bae822affbc0888b($otpl_root)
		{
			$otpl_data = $otpl_root->getData();
			/*--------------OTPL source file Start----------------*/
			?><?php echo '<?php
'; ?>    /**
            * Auto generated file
            *
            * INFO: you are free to edit it,
            * but make sure to know what you are doing.
            *
            * Proudly With: <?php echo($otpl_data['oz_version_name']); ?><?php echo '
	 '; ?>* Time: <?php echo($otpl_data['oz_time']); ?><?php echo '
	 '; ?>*/

            // Protect from unauthorized access/include
            define( 'OZ_SELF_SECURITY_CHECK', 1 );

            // Don't forget to use DS instead of \ or / and allways add the last DS to your directories path
            define( 'DS', DIRECTORY_SEPARATOR );

            // You can define the path to your specific ozone framework directory
            define( 'OZ_OZONE_DIR', __DIR__ . DS . 'oz' . DS );

            // You can define the path to your ozone app directory here
            define( 'OZ_APP_DIR', __DIR__ . DS . 'app' . DS );

            include_once OZ_OZONE_DIR . 'OZone.php';

            // Add project namespace root directory
            \OZONE\OZ\Loader\ClassLoader::addNamespace( '\<?php echo($otpl_data['oz_project_namespace']); ?>', OZ_APP_DIR );

            // Execute OZone only if the incoming request is for the api
            // Else we are in www/index.php
            if( defined('OZ_OZONE_IS_WWW') ) {
            if ( !defined( 'OZ_OZONE_DEFAULT_APIKEY' ) ) {
            throw new \OZONE\OZ\Exceptions\OZoneInternalError('OZ_DEFAULT_APIKEY_NOT_DEFINED');
            }
            } else {
            \OZONE\OZ\OZone::execute( new \<?php echo($otpl_data['oz_project_namespace']); ?>\<?php echo($otpl_data['oz_project_class']); ?>() );
            }
			<?php
			/*--------------OTPL source file End----------------*/
		}
	}