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
		'src_path'     => "/mnt/Emile/silo/ozone/oz/oz_templates/gen/sample.app.otpl",
		'compile_time' => 1504974759,
		'func_name'    => "otpl_func_061d3551a85b3bb44235ada391761af2"
	])
	) {
		function otpl_func_061d3551a85b3bb44235ada391761af2($otpl_root)
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

            namespace <?php echo($otpl_data['oz_project_namespace']); ?>;

            use OZONE\OZ\Exceptions\OZoneBaseException;
            use OZONE\OZ\App\AppInterface;

            defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

            final class <?php echo($otpl_data['oz_project_class']); ?><?php echo ' '; ?>implements AppInterface
            {

            /**
            * {@inheritdoc}
            */
            public static function onInit() {}

            /**
            * {@inheritdoc}
            */
            public static function onError( OZoneBaseException $error )
            {
            return false;
            }
            }
			<?php
			/*--------------OTPL source file End----------------*/
		}
	}