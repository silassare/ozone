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

namespace OZONE\OZ\Auth\Services;

use Gobl\DBAL\Types\TypeString;
use OZONE\OZ\Auth\Auth;
use OZONE\OZ\Auth\AuthScope;
use OZONE\OZ\Auth\AuthSecretType;
use OZONE\OZ\Core\Service;
use OZONE\OZ\Exceptions\InvalidFormException;
use OZONE\OZ\Forms\Field;
use OZONE\OZ\Forms\Form;
use OZONE\OZ\Forms\FormData;
use OZONE\OZ\Http\Response;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;

/**
 * Class AuthService.
 */
class AuthService extends Service
{
	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router->post('/auth/:auth_ref/authorize', static function (RouteInfo $ri) {
			return (new self($ri->getContext()))->authorize($ri, $ri->getCleanFormData());
		})
			   ->form(function () {
				   return self::buildAuthorizeForm();
			   });
		$router->get('/auth/:auth_ref/state', static function (RouteInfo $ri) {
			return (new self($ri->getContext()))->state($ri);
		});
		$router->post('/auth/:auth_ref/refresh', static function (RouteInfo $ri) {
			return (new self($ri->getContext()))->refresh($ri, $ri->getCleanFormData());
		})
			   ->form(function () {
				   return self::buildRefreshForm();
			   });
		$router->post('/auth/:auth_ref/cancel', static function (RouteInfo $ri) {
			return (new self($ri->getContext()))->cancel($ri);
		});
	}

	/**
	 * @param \OZONE\OZ\Router\RouteInfo $ri
	 * @param \OZONE\OZ\Forms\FormData   $fd
	 *
	 * @return \OZONE\OZ\Http\Response
	 *
	 * @throws \OZONE\OZ\Exceptions\NotFoundException
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
	 */
	public function refresh(RouteInfo $ri, FormData $fd): Response
	{
		$ref         = $ri->getParam('auth_ref');
		$refresh_key = $fd->get('refresh_key');

		$auth = Auth::getRequiredByRef($ref);

		$provider = Auth::getAuthProvider($auth->provider, $ri->getContext(), AuthScope::from($auth));

		$provider->getCredentials()
				 ->setReference($ref)
				 ->setRefreshKey($refresh_key);

		$provider->refresh();

		$this->getJSONResponse()
			 ->merge($provider->getJSONResponse());

		return $this->respond();
	}

	/**
	 * @param \OZONE\OZ\Router\RouteInfo $ri
	 *
	 * @return \OZONE\OZ\Http\Response
	 *
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
	 * @throws \OZONE\OZ\Exceptions\NotFoundException
	 */
	public function state(RouteInfo $ri): Response
	{
		$ref = $ri->getParam('auth_ref');

		$auth = Auth::getRequiredByRef($ref);

		$provider = Auth::getAuthProvider($auth->provider, $ri->getContext(), AuthScope::from($auth));

		$provider->getCredentials()
			 ->setReference($ref);

		$this->getJSONResponse()
			 ->setDone()
			 ->setData([
				 'auth_state' => $provider->getState()->value,
			 ]);

		return $this->respond();
	}

	/**
	 * @param \OZONE\OZ\Router\RouteInfo $ri
	 *
	 * @return \OZONE\OZ\Http\Response
	 *
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
	 * @throws \OZONE\OZ\Exceptions\NotFoundException
	 */
	public function cancel(RouteInfo $ri): Response
	{
		$ref = $ri->getParam('auth_ref');

		$auth = Auth::getRequiredByRef($ref);

		$provider = Auth::getAuthProvider($auth->provider, $ri->getContext(), AuthScope::from($auth));

		$provider->getCredentials()
			 ->setReference($ref);

		$provider->cancel();

		$this->getJSONResponse()
			 ->merge($provider->getJSONResponse());

		return $this->respond();
	}

	/**
	 * @param \OZONE\OZ\Router\RouteInfo $ri
	 * @param \OZONE\OZ\Forms\FormData   $fd
	 *
	 * @return \OZONE\OZ\Http\Response
	 *
	 * @throws \OZONE\OZ\Exceptions\InvalidFormException
	 * @throws \OZONE\OZ\Exceptions\NotFoundException
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
	 */
	public function authorize(RouteInfo $ri, FormData $fd): Response
	{
		$ref = $ri->getParam('auth_ref');

		$auth = Auth::getRequiredByRef($ref);

		$provider = Auth::getAuthProvider($auth->provider, $ri->getContext(), AuthScope::from($auth));

		$provider->getCredentials()
			 ->setReference($ref);

		$code  = $fd->get('code');
		$token = $fd->get('token');

		if (null !== $code) {
			$type = AuthSecretType::CODE;
			$provider->getCredentials()
				 ->setCode($code);
		} elseif (null !== $token) {
			$type = AuthSecretType::TOKEN;
			$provider->getCredentials()
				 ->setToken($token);
		} else {
			throw new InvalidFormException();
		}

		$provider->authorize($type);

		$this->getJSONResponse()
			 ->merge($provider->getJSONResponse());

		return $this->respond();
	}

	/**
	 * @return \OZONE\OZ\Forms\Form
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	private static function buildRefreshForm(): Form
	{
		$fb = new Form();

		$fb->addField(new Field('refresh_key', new TypeString(1), true));

		return $fb;
	}

	/**
	 * @return \OZONE\OZ\Forms\Form
	 */
	private static function buildAuthorizeForm(): Form
	{
		$fb = new Form();

		$fb->addField(new Field('code', new TypeString(), false));
		$fb->addField(new Field('token', new TypeString(), false));

		return $fb;
	}
}
