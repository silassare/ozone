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
		'src_path'     => "/mnt/Emile/silo/ozone/oz/oz_templates/gen/settings.info.otpl",
		'compile_time' => 1504974759,
		'func_name'    => "otpl_func_5a6bb238c001694de478fc1185f47c2a"
	])
	) {
		function otpl_func_5a6bb238c001694de478fc1185f47c2a($otpl_root)
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

            defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

            return <?php echo($otpl_data['oz_settings_str']); ?>;<?php
			/*--------------OTPL source file End----------------*/
		}
	}