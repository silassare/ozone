<?php

	namespace OZONE\OZ\Web\Views;

	use OZONE\OZ\Exceptions\InternalErrorException;
	use OZONE\OZ\Router\RouteInfo;
	use OZONE\OZ\Router\Router;
	use OZONE\OZ\Web\WebViewBase;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class RedirectView extends WebViewBase
	{
		private $compileData = [];

		/**
		 * @return \OZONE\OZ\Http\Response
		 * @throws \OZONE\OZ\Exceptions\BaseException
		 */
		public function mainRoute()
		{
			$context = $this->r->getContext();
			$request = $context->getRequest();
			$url     = $request->getAttribute('url');
			$status  = $request->getAttribute('status');

			if (!filter_var($url, FILTER_VALIDATE_URL)) {
				throw new InternalErrorException('Invalid redirect url.');
			}

			$this->compileData = [
				'oz_redirect_url' => filter_var($url, FILTER_SANITIZE_URL)
			];

			$response = $context->getResponse()
								->withRedirect($url, $status);

			return $this->renderTo($response);
		}

		/**
		 * @inheritdoc
		 */
		public function getCompileData()
		{
			return $this->compileData;
		}

		/**
		 * @inheritdoc
		 */
		public function getTemplate()
		{
			return 'redirect.otpl';
		}

		/**
		 * @inheritdoc
		 */
		public static function registerRoutes(Router $router)
		{
			$router->map('*', '/oz:redirect', function (RouteInfo $r) {
				$view = new RedirectView($r);

				return $view->mainRoute();
			}, ['route:name' => 'oz:redirect']);
		}
	}