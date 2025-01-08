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

namespace OZONE\Core\Auth\Services;

use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\TypeString;
use OZONE\Core\App\Service;
use OZONE\Core\Auth\Auth;
use OZONE\Core\Auth\AuthSecretType;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\UnauthorizedActionException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use Throwable;

/**
 * Class AuthService.
 */
class AuthService extends Service
{
	/**
	 * {@inheritDoc}
	 *
	 * @throws Throwable
	 */
	public static function registerRoutes(Router $router): void
	{
		$router->group('/auth/:' . OZAuth::COL_REF, static function (Router $router) {
			$router->post('/authorize', static function (RouteInfo $ri) {
				return (new self($ri))->authorize($ri, $ri->getCleanFormData());
			})
				->form(self::buildAuthorizeForm(...));

			$router->post('/refresh', static function (RouteInfo $ri) {
				return (new self($ri))->refresh($ri, $ri->getCleanFormData());
			})
				->form(self::buildRefreshForm(...));

			$router->get('/state', static function (RouteInfo $ri) {
				return (new self($ri))->state($ri);
			});

			$router->post('/cancel', static function (RouteInfo $ri) {
				return (new self($ri))->cancel($ri);
			});
		});
	}

	/**
	 * @param RouteInfo $ri
	 * @param FormData  $fd
	 *
	 * @return Response
	 *
	 * @throws NotFoundException
	 * @throws UnauthorizedActionException
	 */
	public function refresh(RouteInfo $ri, FormData $fd): Response
	{
		$ref         = $ri->param(OZAuth::COL_REF);
		$refresh_key = $fd->get('refresh_key');

		$auth = Auth::getRequired($ref);

		$provider = Auth::provider($ri->getContext(), $auth);

		$provider->getCredentials()
			->setReference($ref)
			->setRefreshKey($refresh_key);

		$provider->refresh();

		$this->json()
			->merge($provider->getJSONResponse());

		return $this->respond();
	}

	/**
	 * @param RouteInfo $ri
	 *
	 * @return Response
	 *
	 * @throws UnauthorizedActionException
	 * @throws NotFoundException
	 */
	public function state(RouteInfo $ri): Response
	{
		$ref = $ri->param(OZAuth::COL_REF);

		$auth = Auth::getRequired($ref);

		$provider = Auth::provider($ri->getContext(), $auth);

		$provider->getCredentials()
			->setReference($ref);

		$this->json()
			->setDone()
			->setData([
				OZAuth::COL_STATE => $provider->getState()->value,
			]);

		return $this->respond();
	}

	/**
	 * @param RouteInfo $ri
	 *
	 * @return Response
	 *
	 * @throws UnauthorizedActionException
	 * @throws NotFoundException
	 */
	public function cancel(RouteInfo $ri): Response
	{
		$ref = $ri->param(OZAuth::COL_REF);

		$auth = Auth::getRequired($ref);

		$provider = Auth::provider($ri->getContext(), $auth);

		$provider->getCredentials()
			->setReference($ref);

		$provider->cancel();

		$this->json()
			->merge($provider->getJSONResponse());

		return $this->respond();
	}

	/**
	 * @param RouteInfo $ri
	 * @param FormData  $fd
	 *
	 * @return Response
	 *
	 * @throws InvalidFormException
	 * @throws NotFoundException
	 * @throws UnauthorizedActionException
	 */
	public function authorize(RouteInfo $ri, FormData $fd): Response
	{
		$ref = $ri->param(OZAuth::COL_REF);

		$auth = Auth::getRequired($ref);

		$provider = Auth::provider($ri->getContext(), $auth);

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

		$this->json()
			->merge($provider->getJSONResponse());

		return $this->respond();
	}

	/**
	 * @return Form
	 *
	 * @throws TypesException
	 */
	private static function buildRefreshForm(): Form
	{
		$fb = new Form();

		$fb->field('refresh_key')
			->type(new TypeString(16))
			->required();

		return $fb;
	}

	/**
	 * @return Form
	 */
	private static function buildAuthorizeForm(): Form
	{
		$fb = new Form();

		$fb->field('code');
		$fb->field('token');

		return $fb;
	}
}
