<?php

	namespace OZONE\OZ\WebRoute\Services;

	use OZONE\OZ\OZone;
	use OZONE\OZ\WebRoute\WebRoute;
	use OZONE\OZ\Core\OZoneAssert;
	use OZONE\OZ\Core\OZoneUri;
	use OZONE\OZ\Core\OZoneService;
	use OZONE\OZ\Exceptions\OZoneNotFoundException;

	final class RouteRunner extends OZoneService
	{

		public function __construct()
		{
			parent::__construct();
		}

		/**
		 * {@inheritdoc}
		 */
		public function execute($request = [])
		{
			if (isset($request['oz_route_path'])) {
				$route_path = $request['oz_route_path'];
			} else {
				$route_path = OZoneUri::getUriPart('oz_uri_service_extra');
			}

			self::run($route_path, $request);
		}

		/**
		 * @param string $route_path
		 * @param array  $request
		 */
		private static function run($route_path, array $request)
		{
			// it may be a call from another service
			// so check again
			OZoneAssert::assertAuthorizeAction(WebRoute::routeExists($route_path), new OZoneNotFoundException(null, ['oz_route_current_path' => $route_path]));

			$route            = WebRoute::findRoute($route_path);
			$route_class_name = $route['handler'];

			OZone::obj($route_class_name, $request)
				 ->render()
				 ->serve();
		}
	}