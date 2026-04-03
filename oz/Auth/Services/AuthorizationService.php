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
use Override;
use OZONE\Core\App\Service;
use OZONE\Core\Auth\Auth;
use OZONE\Core\Auth\Enums\AuthorizationSecretType;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\UnauthorizedException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Http\Response;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use Throwable;

/**
 * Class AuthorizationService.
 */
class AuthorizationService extends Service
{
	public const ROUTE_AUTHORIZE = 'oz:auth:authorize';
	public const ROUTE_REFRESH   = 'oz:auth:refresh';
	public const ROUTE_STATE     = 'oz:auth:state';
	public const ROUTE_CANCEL    = 'oz:auth:cancel';

	/**
	 * {@inheritDoc}
	 *
	 * @throws Throwable
	 */
	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$router->group('/auth/:' . OZAuth::COL_REF, static function (Router $router) {
			$router->post('/authorize', static fn (RouteInfo $ri) => (new self($ri))->authorize($ri, $ri->getCleanFormData()))
				->name(self::ROUTE_AUTHORIZE)
				->form(self::buildAuthorizeForm(...));

			$router->post('/refresh', static fn (RouteInfo $ri) => (new self($ri))->refresh($ri, $ri->getCleanFormData()))
				->name(self::ROUTE_REFRESH)
				->form(self::buildRefreshForm(...));

			$router->get('/state', static fn (RouteInfo $ri) => (new self($ri))->state($ri))
				->name(self::ROUTE_STATE);

			$router->post('/cancel', static fn (RouteInfo $ri) => (new self($ri))->cancel($ri, $ri->getCleanFormData()))
				->name(self::ROUTE_CANCEL)
				->form(self::buildCancelForm(...));
		});
	}

	/**
	 * @throws NotFoundException
	 * @throws UnauthorizedException
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
	 * @throws UnauthorizedException
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
	 * @throws UnauthorizedException
	 * @throws NotFoundException
	 * @throws InvalidFormException
	 */
	public function cancel(RouteInfo $ri, FormData $fd): Response
	{
		$ref         = $ri->param(OZAuth::COL_REF);
		$refresh_key = $fd->get('refresh_key');

		$auth = Auth::getRequired($ref);

		$provider = Auth::provider($ri->getContext(), $auth);

		$provider->getCredentials()
			->setReference($ref)
			->setRefreshKey($refresh_key);

		$provider->cancel();

		$this->json()
			->merge($provider->getJSONResponse());

		return $this->respond();
	}

	/**
	 * @throws InvalidFormException
	 * @throws NotFoundException
	 * @throws UnauthorizedException
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
			$type = AuthorizationSecretType::CODE;
			$provider->getCredentials()
				->setCode($code);
		} elseif (null !== $token) {
			$type = AuthorizationSecretType::TOKEN;
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
	 * {@inheritDoc}
	 */
	#[Override]
	public static function apiDoc(ApiDoc $doc): void
	{
		$tag = $doc->addTag('Authorization', 'Authorization flow endpoints.');

		$doc->addOperationFromRoute(self::ROUTE_AUTHORIZE, 'POST', 'Authorize', [
			$doc->success(['state' => $doc->string('The authorization state.')]),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Auth.authorize',
			'description' => 'Submit a code or token to complete an authorization flow.',
		]);

		$doc->addOperationFromRoute(self::ROUTE_REFRESH, 'POST', 'Refresh', [
			$doc->success(['state' => $doc->string('The authorization state.')]),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Auth.refresh',
			'description' => 'Refresh an authorization flow credentials.',
		]);

		$doc->addOperationFromRoute(self::ROUTE_STATE, 'GET', 'Get State', [
			$doc->success(['state' => $doc->string('The current authorization state.')]),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Auth.state',
			'description' => 'Get the current state of an authorization flow.',
		]);

		$doc->addOperationFromRoute(self::ROUTE_CANCEL, 'POST', 'Cancel', [
			$doc->success([]),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Auth.cancel',
			'description' => 'Cancel an authorization flow. Requires the refresh_key to prevent unauthorized cancellation.',
		]);
	}

	/**
	 * @return Form
	 *
	 * @throws TypesException
	 */
	private static function buildCancelForm(): Form
	{
		$fb = new Form();

		$fb->field('refresh_key')
			->type(new TypeString(16))
			->required();

		return $fb;
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
