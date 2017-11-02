<?php

	namespace OZONE\OZ\WebRoute\Services;

	use OZONE\OZ\OZone;
	use OZONE\OZ\WebRoute\WebRoute;
	use OZONE\OZ\Core\Assert;
	use OZONE\OZ\Core\URIHelper;
	use OZONE\OZ\Core\BaseService;
	use OZONE\OZ\Exceptions\NotFoundException;

	final class RouteRunner extends BaseService
	{

		public function __construct()
		{
			parent::__construct();
		}

		/**
		 * {@inheritdoc}
		 */
		public function execute(array $request = [])
		{
			if (isset($request['oz_route_path'])) {
				$route_path = $request['oz_route_path'];
			} else {
				$route_path = URIHelper::getUriExtra();
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
			Assert::assertAuthorizeAction(WebRoute::routeExists($route_path), new NotFoundException(null, ['oz_route_current_path' => $route_path]));

			$route            = WebRoute::findRoute($route_path);
			$route_class_name = $route['handler'];

			OZone::obj($route_class_name, $request)
				 ->render()
				 ->serve();
		}
	}