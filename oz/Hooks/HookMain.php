<?php
	/**
	 * Copyright (c) 2017-present, Emile Silas Sare
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Hooks;

	use OZONE\OZ\Exceptions\ForbiddenException;
	use OZONE\OZ\Exceptions\MethodNotAllowedException;
	use OZONE\OZ\Exceptions\NotFoundException;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	final class HookMain extends Hook
	{
		/**
		 * @inheritdoc
		 * @throws \Exception
		 */
		public function onNotFound(HookContext $hc)
		{
			$context = $hc->getContext();
			$request = $hc->getRequest();
			$uri     = $request->getUri();

			// is it root
			if ($context->isApiContext() AND $uri->getPath() === '/') {
				// TODO api doc
				// show api usage doc when this condition are met:
				//  - we are in api mode
				//	- debugging or allowed in settings
				// show welcome friendly page when this conditions are met:
				//  - we are in web mode
				throw new ForbiddenException();
			} else {
				throw new NotFoundException();
			}
		}

		/**
		 * @inheritdoc
		 * @throws \Exception
		 */
		public function onMethodNotAllowed(HookContext $hc)
		{
			throw new MethodNotAllowedException();
		}

		/**
		 * @param \OZONE\OZ\Hooks\HookContext $hc
		 *
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 */
		public function onFinish(HookContext $hc)
		{
			$ctx      = $hc->getContext();
			$response = $ctx->getResponse();
			$response = $ctx->getSession()
							->responseReady($response);

			$hc->setResponse($response);
		}
	}