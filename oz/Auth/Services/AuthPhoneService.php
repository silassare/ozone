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

use Gobl\DBAL\Types\TypeBool;
use Gobl\DBAL\Types\TypeString;
use OZONE\OZ\Auth\AuthSecretType;
use OZONE\OZ\Auth\Providers\AuthPhone;
use OZONE\OZ\Columns\Types\TypePhone;
use OZONE\OZ\Core\Service;
use OZONE\OZ\Exceptions\InvalidFormException;
use OZONE\OZ\Forms\Field;
use OZONE\OZ\Forms\Form;
use OZONE\OZ\Forms\FormData;
use OZONE\OZ\Forms\FormRule;
use OZONE\OZ\Forms\TypesSwitcher;
use OZONE\OZ\Http\Response;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;

/**
 * Class AuthPhoneService.
 */
class AuthPhoneService extends Service
{
	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router->post('/auth/phone', static function (RouteInfo $ri) {
			$s = new self($ri->getContext());

			return $s->initPhone($ri, $ri->getCleanFormData());
		})
			->form(function () {
				return self::buildInitForm();
			});

		$router->post('/auth/:auth_ref/authorize', static function (RouteInfo $ri) {
			$s = new self($ri->getContext());

			return $s->authorize($ri, $ri->getCleanFormData());
		})
			->form(function () {
				return self::buildAuthorizeForm();
			});
		$router->get('/auth/:auth_ref/state', static function (RouteInfo $ri) {
			$s = new self($ri->getContext());

			return $s->state($ri);
		});
		$router->post('/auth/:auth_ref/refresh', static function (RouteInfo $ri) {
			$s = new self($ri->getContext());

			return $s->refresh($ri, $ri->getCleanFormData());
		})
			->form(function () {
				return self::buildRefreshForm();
			});
		$router->post('/auth/:auth_ref/cancel', static function (RouteInfo $ri) {
			$s = new self($ri->getContext());

			return $s->cancel($ri);
		});
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
	public function refresh(RouteInfo $ri, FormData $fd): Response
	{
		$ref         = $ri->getParam('auth_ref');
		$refresh_key = $fd->get('refresh_key');

		$auth = new AuthPhone($ri->getContext());

		$auth->getCredentials()
			->setReference($ref)
			->setRefreshKey($refresh_key);

		$auth->refresh();

		$this->getJSONResponse()
			->merge($auth->getJSONResponse());

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
		$ref  = $ri->getParam('auth_ref');
		$auth = new AuthPhone($ri->getContext());

		$auth->getCredentials()
			->setReference($ref);

		$this->getJSONResponse()
			->setDone()
			->setData([
				'auth_state' => $auth->getState()->value,
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
		$ref  = $ri->getParam('auth_ref');
		$auth = new AuthPhone($ri->getContext());

		$auth->getCredentials()
			->setReference($ref);

		$auth->cancel();

		$this->getJSONResponse()
			->merge($auth->getJSONResponse());

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
		$ref  = $ri->getParam('auth_ref');
		$auth = new AuthPhone($ri->getContext());

		$auth->getCredentials()
			->setReference($ref);

		$code  = $fd->get('code');
		$token = $fd->get('token');

		if (null !== $code) {
			$type = AuthSecretType::CODE;
			$auth->getCredentials()
				->setCode($code);
		} elseif (null !== $token) {
			$type = AuthSecretType::TOKEN;
			$auth->getCredentials()
				->setToken($token);
		} else {
			throw new InvalidFormException();
		}

		$auth->authorize($type);

		$this->getJSONResponse()
			->merge($auth->getJSONResponse());

		return $this->respond();
	}

	/**
	 * @param \OZONE\OZ\Router\RouteInfo $ri
	 * @param \OZONE\OZ\Forms\FormData   $fd
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	private function initPhone(RouteInfo $ri, FormData $fd): Response
	{
		$phone = $fd->get('phone');

		$auth = new AuthPhone($ri->getContext());

		$auth->getScope()->setValue($phone);
		$auth->generate();

		$this->getJSONResponse()
			->merge($auth->getJSONResponse());

		return $this->respond();
	}

	/**
	 * @return \OZONE\OZ\Forms\Form
	 */
	private static function buildInitForm(): Form
	{
		$fb = new Form();

		$fb->addField(new Field('registered', new TypeBool(), false));

		$phone = new TypesSwitcher();
		$phone->when((new FormRule())->isNull('registered'), new TypePhone())
			->when((new FormRule())->eq('registered', true), (new TypePhone())->registered())
			->when((new FormRule())->eq('registered', false), (new TypePhone())->notRegistered());

		$fb->addField(new Field('phone', $phone, true));

		return $fb;
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
