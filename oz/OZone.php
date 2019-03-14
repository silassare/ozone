<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ;

	use OZONE\OZ\App\AppInterface;
	use OZONE\OZ\Core\Assert;
	use OZONE\OZ\Core\DbManager;
	use OZONE\OZ\Core\RequestHandler;
	use OZONE\OZ\Core\ResponseHolder;
	use OZONE\OZ\Core\BaseService;
	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\Core\URIHelper;
	use OZONE\OZ\Db\OZClient;
	use OZONE\OZ\Event\EventManager;
	use OZONE\OZ\Exceptions\BaseException;
	use OZONE\OZ\Exceptions\ForbiddenException;
	use OZONE\OZ\Exceptions\InternalErrorException;
	use OZONE\OZ\Exceptions\NotFoundException;
	use OZONE\OZ\Lang\Polyglot;
	use OZONE\OZ\Loader\ClassLoader;
	use OZONE\OZ\User\UsersUtils;

	defined('OZ_SELF_SECURITY_CHECK') or die;

    include_once OZ_OZONE_DIR . 'oz_vendors' . DS . 'autoload.php';
	include_once OZ_OZONE_DIR . 'oz_default' . DS . 'oz_config.php';
	include_once OZ_OZONE_DIR . 'oz_default' . DS . 'oz_define.php';
	include_once OZ_OZONE_DIR . 'oz_default' . DS . 'oz_func.php';

	final class OZone
	{
		/**
		 * service default config
		 *
		 * @var array
		 */
		private static $svc_default = [
			"service_class"   => null,
			"is_file_service" => false,
			"can_serve_resp"  => false,
			"cross_site"      => false,
			"require_session" => true,
			"request_methods" => ['POST', 'GET', 'PUT', 'PATCH', 'DELETE']
		];

		/**
		 * the current running app
		 *
		 * @var AppInterface
		 */
		private static $current_app = null;

		/**
		 * Gets current running app.
		 *
		 * @return AppInterface
		 */
		public static function getCurrentApp()
		{
			return self::$current_app;
		}

		/**
		 * Gets event manager instance.
		 *
		 * @return \OZONE\OZ\Event\EventManager
		 */
		public static function getEventManager()
		{
			return EventManager::getInstance();
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

			Polyglot::init();

			DbManager::init();

			RequestHandler::initCheck();

			self::runApp();
		}

		/**
		 * The ozone app running logic is here
		 *
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \ReflectionException
		 * @throws \Exception
		 */
		private static function runApp()
		{
			self::getCurrentApp()
				->onInit();

			$req_svc = URIHelper::getUriService();

			if (!empty($req_svc)) {
				try {
					$svc    = SettingsManager::get('oz.services.list', $req_svc);
					$svc_ok = false;

					if (!empty($svc)) {
						$svc = array_merge(self::$svc_default, $svc);

						if (!empty($svc['service_class']) AND ClassLoader::exists($svc['service_class'])) {
							$svc_ok = true;
						}
					}

					if ($svc_ok === true) {
						Assert::assertSafeRequestMethod($svc['request_methods']);

						/** @var BaseService $svc_obj */
						$svc_obj = OZone::obj($svc['service_class']);

						$svc_obj->execute($_REQUEST);

						if (!$svc['can_serve_resp']) {
							OZone::say($svc_obj->getResponseHolder());
						}
					} else {
						throw new NotFoundException('OZ_SERVICE_NOT_FOUND');
					}
				} catch (InternalErrorException $e) {
					throw $e;
				} catch (BaseException $e) {
					$cancel = self::getCurrentApp()
								  ->onError($e);

					if ($cancel) {
						oz_logger('--------Error canceled--------');
						oz_logger($e);
						// TODO
						// What happen after this? :)
						// Silence is gold rule or ...?
					} else {
						oz_logger($e);
						$e->procedure();
					}
				}
			} else {
				RequestHandler::attackProcedure(new ForbiddenException(), 'Requested service should not be empty here, something went wrong or we are under attack.');
			}
		}

		/**
		 * Create instance of class for a given class name and arguments.
		 *
		 * @param string $class_name The full qualified class name to instantiate.
		 *
		 * @return object
		 * @throws \ReflectionException
		 */
		public static function obj($class_name)
		{
			$c_args = func_get_args();

			array_shift($c_args);

			return ClassLoader::instantiateClass($class_name, $c_args);
		}

		/**
		 * Gets all declared services
		 *
		 * @return array|null
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 */
		public static function getAllServices()
		{
			return SettingsManager::get('oz.services.list');
		}

		/**
		 * Gets all declared file services
		 *
		 * @return array
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
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
		 * Send response to client in json format
		 *
		 * @param \OZONE\OZ\Core\ResponseHolder $response_holder
		 *
		 * @throws \Exception
		 */
		public static function sayJson(ResponseHolder $response_holder)
		{
			$data          = $response_holder->getResponse();
			$now           = time();
			$data['utime'] = $now;

			if (UsersUtils::userVerified()) {
				// set session expire time
				/**
				 * @var $client \OZONE\OZ\Db\OZClient
				 */
				$client = RequestHandler::getCurrentClient();

				if ($client instanceof OZClient) {
					$lifetime      = 1 * $client->getSessionLifeTime();
					$data["stime"] = $now + $lifetime;
				}
			}

			header('Content-type: application/json');
			echo json_encode($data);
			exit;
		}

		/**
		 * Send response to client in different format
		 *
		 * @param \OZONE\OZ\Core\ResponseHolder $response_holder
		 *
		 * @throws \Exception
		 */
		public static function say(ResponseHolder $response_holder)
		{
			// TODO output in xml
			self::sayJson($response_holder);
		}
	}