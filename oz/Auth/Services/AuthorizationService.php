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
use OZONE\Core\Forms\FormDataClean;
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
	 * The route parameter holding the authorization reference.
	 *
	 * A plain name, not `OZAuth::COL_REF`: reading that constant loads the generated ORM class, and
	 * route registration must not -- loading an ORM class initializes the database, so every
	 * request would build the schema just to declare these routes.
	 */
	private const REF_PARAM = 'auth_ref';

	/**
	 * {@inheritDoc}
	 *
	 * @throws Throwable
	 */
	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$router->group('/auth/:' . self::REF_PARAM, static function (Router $router): void {
			$authorize = static fn (RouteInfo $ri) => (new self($ri))->authorize($ri, $ri->getCleanFormData());
			$refresh   = static fn (RouteInfo $ri) => (new self($ri))->refresh($ri, $ri->getCleanFormData());

			$router->post('/authorize', $authorize)
				->name(self::ROUTE_AUTHORIZE)
				->form(self::buildAuthorizeForm(...));

			$router->post('/refresh', $refresh)
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
	public function refresh(RouteInfo $ri, FormDataClean $fd): Response
	{
		$ref         = $ri->param(self::REF_PARAM);
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
		$ref = $ri->param(self::REF_PARAM);

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
	public function cancel(RouteInfo $ri, FormDataClean $fd): Response
	{
		$ref         = $ri->param(self::REF_PARAM);
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
	public function authorize(RouteInfo $ri, FormDataClean $fd): Response
	{
		$ref = $ri->param(self::REF_PARAM);

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

		$ref_param = $doc->parameter(
			OZAuth::COL_REF,
			$doc->string('The authorization flow reference.'),
			'Authorization flow reference, returned by the endpoint that started the flow.',
			'path'
		);

		$state_schema = $doc->object([
			'ref'         => $doc->string('The authorization reference.'),
			'state'       => $doc->string('The current authorization state (`pending`, `authorized`, `expired`, ...).'),
			'expires_at'  => $doc->integer('UNIX timestamp when this flow expires, or `null`.', ['nullable' => true]),
		], ['description' => 'Current authorization flow state.']);

		$doc->addOperationFromRoute(self::ROUTE_AUTHORIZE, 'POST', 'Authorize', [
			$doc->success(['state' => $state_schema], 'Authorization accepted.', 'OZ_AUTH_AUTHORIZED'),
			$doc->error([], 'Invalid or already-used code / token.', 'OZ_AUTH_CODE_INVALID', 200),
			$doc->error([], 'Flow has expired.', 'OZ_AUTH_EXPIRED', 200),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Auth.authorize',
			'description' => 'Complete a flow by submitting a `code` (sent via email or SMS) '
				. 'or a `token`. Which is expected depends on the provider.',
			'parameters'  => [$ref_param],
		]);

		$doc->addOperationFromRoute(self::ROUTE_REFRESH, 'POST', 'Refresh', [
			$doc->success(['state' => $state_schema], 'Credentials refreshed.', 'OZ_AUTH_REFRESHED'),
			$doc->error([], 'Invalid or expired `refresh_key`.', 'OZ_AUTH_REFRESH_KEY_INVALID', 200),
			$doc->error([], 'Flow has expired.', 'OZ_AUTH_EXPIRED', 200),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Auth.refresh',
			'description' => 'Refresh an authorization flow (re-send the code or regenerate credentials). '
				. 'Requires the `refresh_key` returned when the flow was created.',
			'parameters'  => [$ref_param],
		]);

		$doc->addOperationFromRoute(self::ROUTE_STATE, 'GET', 'Get Authorization State', [
			$doc->success(['state' => $state_schema], 'Current authorization state.'),
			$doc->error([], 'Flow not found.', 'OZ_AUTH_NOT_FOUND', 200),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Auth.state',
			'description' => 'Retrieve the current state of an authorization flow without advancing it.',
			'parameters'  => [$ref_param],
		]);

		$doc->addOperationFromRoute(self::ROUTE_CANCEL, 'POST', 'Cancel Authorization', [
			$doc->success([], 'Flow cancelled.'),
			$doc->error([], 'Invalid or expired `refresh_key`.', 'OZ_AUTH_REFRESH_KEY_INVALID', 200),
			$doc->error([], 'Flow not found.', 'OZ_AUTH_NOT_FOUND', 200),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Auth.cancel',
			'description' => 'Cancel an authorization flow. Requires the `refresh_key`, so only its owner can.',
			'parameters'  => [$ref_param],
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
