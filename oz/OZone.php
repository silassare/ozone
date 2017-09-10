<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ;

	use OZONE\OZ\App\AppInterface;
	use OZONE\OZ\Core\OZoneAssert;
	use OZONE\OZ\Core\OZoneRequest;
	use OZONE\OZ\Core\OZoneService;
	use OZONE\OZ\Core\OZoneSettings;
	use OZONE\OZ\Core\OZoneUri;
	use OZONE\OZ\Exceptions\OZoneBaseException;
	use OZONE\OZ\Exceptions\OZoneForbiddenException;
	use OZONE\OZ\Exceptions\OZoneInternalError;
	use OZONE\OZ\Exceptions\OZoneNotFoundException;
	use OZONE\OZ\Lang\OZoneLang;
	use OZONE\OZ\Loader\ClassLoader;
	use OZONE\OZ\Utils\OZoneStr;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	include_once OZ_OZONE_DIR . 'oz_default' . DS . 'oz_config.php';
	include_once OZ_OZONE_DIR . 'oz_default' . DS . 'oz_define.php';
	include_once OZ_OZONE_DIR . 'Loader' . DS . 'ClassLoader.php';

	ClassLoader::addNamespace('\OZONE\OZ', OZ_OZONE_DIR);
	ClassLoader::addDir(OZ_OZONE_DIR . 'oz_vendors', true, 1);

	include_once OZ_OZONE_DIR . 'oz_default' . DS . 'oz_func.php';

	final class OZone
	{
		/**
		 * service default config
		 *
		 * @var array
		 */
		private static $svc_default = [
			"internal_name"   => null,
			"is_file_service" => false,
			"can_serve_resp"  => false,
			"cross_site"      => false,
			"require_client"  => true,
			"req_methods"     => ['POST', 'GET', 'PUT', 'DELETE']
		];

		/**
		 * the current running app
		 *
		 * @var AppInterface
		 */
		private static $current_app = null;

		/**
		 * @return mixed
		 */
		public static function getCurrentApp()
		{
			return self::$current_app;
		}

		/**
		 * ozone main entry point
		 *
		 * @param \OZONE\OZ\App\AppInterface $app
		 *
		 * @throws \Exception    when \OZONE\OZ\OZone::execute is called twice
		 */
		public static function execute(AppInterface $app)
		{
			if (!empty(self::$current_app)) {
				throw new \Exception("Your app is already running.");
			}

			self::$current_app = $app;

			OZoneLang::init();

			OZoneRequest::initCheck();

			self::runApp();
		}

		/**
		 * the ozone app running logic is here
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneInternalError
		 */
		private static function runApp()
		{
			self::getCurrentApp()
				->onInit();

			$req_svc = OZoneUri::getUriPart('oz_uri_service');

			if (!empty($req_svc)) {
				try {
					$svc    = OZoneSettings::get('oz.services.list', $req_svc);
					$svc_ok = false;

					if (!empty($svc)) {
						$svc = array_merge(self::$svc_default, $svc);

						if (!empty($svc['internal_name']) AND ClassLoader::exists($svc['internal_name'])) {
							$svc_ok = true;
						}
					}

					if ($svc_ok === true) {
						OZoneAssert::assertSafeRequestMethod($svc['req_methods']);

						/** @var OZoneService $svc_obj */
						$svc_obj = OZone::obj($svc['internal_name']);

						$svc_obj->execute($_REQUEST);

						if (!$svc['can_serve_resp']) OZone::say($svc_obj->getServiceResponse());
					} else {
						throw new OZoneNotFoundException('OZ_SERVICE_NOT_FOUND');
					}
				} catch (OZoneInternalError $e) {
					throw $e;
				} catch (OZoneBaseException $e) {
					$cancel = self::getCurrentApp()
								  ->onError($e);

					oz_logger(($cancel ? 'CANCELED: Message :' . $e : '' . $e));

					if (!$cancel) {
						$e->procedure();
					}
				}
			} else {
				// SILO:: nous sommes peut-etre victime d'une attaque
				OZoneRequest::attackProcedure(new OZoneForbiddenException(), 'Requested service should not be empty here, something went wrong or we are under attack.');
			}
		}

		/**
		 * Create instance of class for a given class name and arguments.
		 *
		 * @param string $class_name The full qualified class name to instantiate.
		 *
		 * @return object
		 */
		public static function obj($class_name)
		{
			$c_args = func_get_args();

			array_shift($c_args);

			return ClassLoader::instantiateClass($class_name, $c_args);
		}

		/**
		 * get all declared services
		 *
		 * @return array|null
		 * @throws \OZONE\OZ\Exceptions\OZoneInternalError
		 */
		public static function getAllServices()
		{
			return OZoneSettings::get('oz.services.list');
		}

		/**
		 * get all declared file services
		 *
		 * @return array
		 */
		public static function getFileServices()
		{
			$services = self::getAllServices();
			$ans      = [];

			foreach ($services as $key => $svc) {
				if ($svc['is_file_service']) {
					$ans[$key] = $svc;
				}
			}

			return $ans;
		}

		/**
		 * output response in json format for client
		 *
		 * @param array $data
		 */
		public static function sayJson($data)
		{
			$data['utime'] = time();

			// reponse vers l'application cliente au format JSON
			header('Content-type: application/json');
			echo json_encode(OZoneStr::encodeFix($data));
			exit;
		}

		/**
		 * output response for client in different format
		 *
		 * @param array $resp
		 */
		public static function say($resp)
		{
			// TODO
			// output in xml
			// output in html (build custom page)
			self::sayJson($resp);
		}
	}