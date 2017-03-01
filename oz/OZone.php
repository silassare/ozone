<?php

	//SILO:: Protect from unauthorized access/include
	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	if ( !defined( 'DS' ) ) {
		define( 'DS', DIRECTORY_SEPARATOR );
	}

	if ( !defined( 'OZ_ROOT_DIR' ) ) {
		define( 'OZ_ROOT_DIR', __DIR__ . DS );
	}

	if ( !defined( 'OZ_APP_DIR' ) ) {
		define( 'OZ_APP_DIR', OZ_ROOT_DIR . 'app' . DS );
	}

	include_once OZ_ROOT_DIR . 'oz' . DS . 'oz_default' . DS . 'oz_config.php';
	include_once OZ_ROOT_DIR . 'oz' . DS . 'oz_default' . DS . 'oz_define.php';

	include_once OZ_OZONE_DIR . 'oz_core' . DS . 'OZoneClassLoader.php';

	OZoneClassLoader::addDirs( array(
		OZ_OZONE_DIR,
		OZ_OZONE_DIR . 'oz_admin',
		OZ_OZONE_DIR . 'oz_core',
		OZ_OZONE_DIR . 'oz_errors',
		OZ_APP_SERVICES_DIR,
		OZ_APP_CORE_DIR,
		array( OZ_OZONE_DIR . 'oz_lib', true, 1 ) ,
		array( OZ_OZONE_DIR . 'oz_plugins', true, 1 )
	) );

	include_once OZ_ROOT_DIR . 'oz' . DS . 'oz_default' . DS . 'oz_func.php';
	include_once OZ_APP_DIR . 'app_config.php';

	include_once OZ_APP_DIR . 'OZoneApp.php';

	final class OZone {
		private static $svc_default = array(
			"internal_name"   => null,
			"is_file_service" => false,
			"can_serve_resp"  => false,
			"cross_site"      => false,
			"require_client"  => true,
			"req_methods"     => [ "POST", "GET" ]
		);

		public static function execute() {
			OZoneRequest::initCheck();

			self::runApp();
		}

		private static function runApp() {

			OZoneApp::onInit();

			if ( !empty( $_REQUEST[ 'oz_req_service' ] ) ) {

				try {

					$req_svc = $_REQUEST[ 'oz_req_service' ];
					$svc = self::getService( $req_svc );

					if ( !empty( $svc ) ) {
						$svc = array_merge( self::$svc_default, $svc );
						$svc_in_name = $svc[ 'internal_name' ];

						if ( !empty( $svc_in_name ) AND OZoneClassLoader::exists( $svc_in_name ) ) {
							define( 'OZ_REQUEST_SERVICE', $svc_in_name );
						}
					}

					if ( defined( 'OZ_REQUEST_SERVICE' ) ) {

						OZoneAssert::assertSafeRequestMethod( $svc[ 'req_methods' ] );

						OZone::obj( OZ_REQUEST_SERVICE )->execute( $_REQUEST );

						if ( !$svc[ 'can_serve_resp' ] )
							OZone::say( OZoneResponsesHolder::getResponses() );

					} else {
						throw new OZoneErrorNotFound();
					}

				} catch ( OZoneError $e ) {

					$cancel = OZoneApp::onError( $e );

					oz_logger( ( $cancel ? 'OZ_ERROR_CANCELED : Message :' . $e : '' . $e ) );

					if ( !$cancel ) {
						$e->procedure();
					}
				}

			} else {
				//SILO:: nous sommes peut-etre victime d'une attaque
				OZoneRequest::attackProcedure();
			}
		}

		public static function obj( $class_name, $args = null ) {
			$c_args = func_get_args();

			array_shift( $c_args );

			return OZoneClassLoader::instantiateClass( $class_name, $c_args );
		}

		public static function getServices() {
			return OZoneSettings::get( 'oz.services.list' );
		}

		public static function getService( $service_name ) {
			return OZoneSettings::get( 'oz.services.list', $service_name );
		}

		public static function getFileServices() {
			$services = self::getServices();
			$ans = array();

			foreach ( $services as $key => $svc ) {

				if ( $svc[ 'is_file_service' ] ) {
					$ans[ $key ] = $svc;
				}
			}

			return $ans;
		}

		public static function sayJson( $data ) {
			$data[ 'utime' ] = time();

			//reponse vers l'application cliente au format JSON
			header( 'Content-type: application/json' );
			echo json_encode( OZoneStr::encodeFix( $data ) );
			exit;
		}

		public static function say( $resp ) {
			$api_ans = $resp[ OZ_REQUEST_SERVICE ];

			self::sayJson( $api_ans );
		}
	}
