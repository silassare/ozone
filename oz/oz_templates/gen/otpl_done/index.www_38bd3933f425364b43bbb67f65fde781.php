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
		'src_path'     => "/mnt/Emile/silo/ozone/oz/oz_templates/gen/index.www.otpl",
		'compile_time' => 1504974759,
		'func_name'    => "otpl_func_978ac6f18046ddfd37bea8e9fa3295ee"
	])
	) {
		function otpl_func_978ac6f18046ddfd37bea8e9fa3295ee($otpl_root)
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

            // Informs OZone that we are in www mode
            define( 'OZ_OZONE_IS_WWW', true );
            define( 'OZ_OZONE_DEFAULT_APIKEY', '<?php echo($otpl_data['oz_default_apikey']); ?>' );

            include_once '../api/index.php';

            // Add settings source
            \OZONE\OZ\Core\OZoneSettings::addSource( __DIR__ . DS . 'oz_private'. DS . 'oz_settings' );

            // Add a new templates source
            \OZONE\OZ\FS\OZoneTemplates::addSource( __DIR__ . DS . 'oz_private'. DS . 'oz_templates' );

            // Add project namespace root directory
            \OZONE\OZ\Loader\ClassLoader::addNamespace( '\<?php echo($otpl_data['oz_project_namespace']); ?>', __DIR__ . DS . 'oz_private' );

            // Execute OZone
            \OZONE\OZ\OZone::execute( new \<?php echo($otpl_data['oz_project_namespace']); ?>\<?php echo($otpl_data['oz_project_class']); ?>() );<?php
			/*--------------OTPL source file End----------------*/
		}
	}