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
		$router->group('/auth/:auth_ref', function (Router $router) {
			$router->post('/authorize', static function (RouteInfo $ri) {
				return (new self($ri))->authorize($ri, $ri->getCleanFormData());
			})->form(self::buildAuthorizeForm(...));

			$router->post('/refresh', static function (RouteInfo $ri) {
				return (new self($ri))->refresh($ri, $ri->getCleanFormData());
			})->form(self::buildRefreshForm(...));

			$router->get('/state', static function (RouteInfo $ri) {
				return (new self($ri))->state($ri);
			});

			$router->post('/cancel', static function (RouteInfo $ri) {
				return (new self($ri))->cancel($ri);
			});
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

		$fb->field('refresh_key')->type(new TypeString(16))->required();

		return $fb;
	}

	/**
	 * @return \OZONE\OZ\Forms\Form
	 */
	private static function buildAuthorizeForm(): Form
	{
		$fb = new Form();

		$fb->field('code');
		$fb->field('token');

		return $fb;
	}
}
