<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace OZONE\Core\Router\Guards;

use Gobl\DBAL\Types\TypeString;
use OZONE\Core\App\Context;
use OZONE\Core\App\JSONResponse;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\UnauthorizedActionException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Router\Interfaces\RouteGuardInterface;
use OZONE\Core\Router\Views\AccessGrantView;
use OZONE\Core\Utils\Hasher;

/**
 * Class AbstractRouteGuard.
 */
abstract class AbstractRouteGuard implements RouteGuardInterface
{
	protected string $username_field = 'user';
	protected string $password_field = 'password';

	/**
	 * This will show a custom form,
	 * and makes sure the form is submitted and is valid.
	 *
	 * @throws UnauthorizedActionException
	 * @throws InvalidFormException
	 */
	protected function requireForm(Context $context, Form $form): FormData
	{
		$request = $context->getRequest();
		$state   = $context->requireAuthStore();
		$uri     = $context->getRequest()
			->getUri();

		$reference  = Hasher::hash32((string) $uri);
		$form_key   = \sprintf('route_guard.clean_forms.%s', $reference);
		$clean_form = $state->get($form_key);

		if (\is_array($clean_form)) {
			return new FormData($clean_form);
		}

		$req_grant_ref = $request->getUnsafeFormField('grant_form_ref');

		if ($req_grant_ref === $reference) {
			$clean_form = $form->validate($request->getUnsafeFormData());

			$state->set($form_key, $clean_form);

			return $clean_form;
		}

		$form->setSubmitTo($uri);
		$form->field('grant_form_ref')
			->type((new TypeString())->default($reference))
			->required();

		$exception = new UnauthorizedActionException();

		if ($context->shouldReturnJSON()) {
			$json = new JSONResponse();

			$json->setError($exception->getMessage())
				->setForm($form);

			$response = $context->getResponse()
				->withJson($json);
		} else {
			$view     = new AccessGrantView($context);
			$response = $view->renderAccessGrantForm($form);
		}

		throw $exception->setCustomResponse($response);
	}
}
