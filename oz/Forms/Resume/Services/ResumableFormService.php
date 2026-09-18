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

namespace OZONE\Core\Forms\Resume\Services;

use Override;
use OZONE\Core\App\Service;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\BadRequestException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Forms\Resume\FormResumeRouteInterceptor;
use OZONE\Core\Forms\Resume\FormSessionManager;
use OZONE\Core\Forms\Resume\FormSessionStep;
use OZONE\Core\Forms\Resume\FormSessionStore;
use OZONE\Core\Forms\Resume\Interfaces\ResumableFormProviderInterface;
use OZONE\Core\Forms\Resume\Traits\ResumableFormServiceApiDocTrait;
use OZONE\Core\Http\Response;
use OZONE\Core\Router\Rates\IPRateLimit;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Class ResumableFormService.
 *
 * HTTP surface of the resumable form system. The state machine lives in
 * {@see FormSessionManager} and persistence in {@see FormSessionStore}; this
 * class only maps requests onto them and results onto responses.
 *
 * Providers implement {@see ResumableFormProviderInterface} and register in the
 * `oz.forms.providers` settings file. The `resume_ref` returned by `init` must be
 * sent back on every subsequent request via a dedicated header.
 *
 * Route overview (group path is configurable via `OZ_RESUMABLE_FORM_SERVICE_ROUTE_GROUP_PATH` in `oz.paths`):
 *
 *  POST {group-path}/:provider/init       - start a new session
 *  GET  {group-path}/:provider/state      - get the current step form
 *  POST {group-path}/:provider/next       - submit the current step, advance
 *  POST {group-path}/:provider/back       - go back to the previous step
 *  POST {group-path}/:provider/cancel     - discard the session
 *  POST {group-path}/:provider/evaluate   - evaluate server-only field/rule visibility
 *
 * The same actions are reachable on any resumable route through
 * {@see FormResumeRouteInterceptor}, via {@see self::handleFromRealContext()}.
 *
 * After the final step `done: true` is returned. Downstream code retrieves the
 * accumulated data with {@see FormSessionStore::requireCompletion()}.
 */
final class ResumableFormService extends Service
{
	use ResumableFormServiceApiDocTrait;

	public const ROUTE_INIT     = 'oz:form:init';
	public const ROUTE_STATE    = 'oz:form:state';
	public const ROUTE_NEXT     = 'oz:form:next';
	public const ROUTE_BACK     = 'oz:form:back';
	public const ROUTE_CANCEL   = 'oz:form:cancel';
	public const ROUTE_EVALUATE = 'oz:form:evaluate';

	// Sub-action values carried by the X-OZONE-Form-Resume-Action header.
	public const ACTION_INIT     = 'init';
	public const ACTION_STATE    = 'state';
	public const ACTION_NEXT     = 'next';
	public const ACTION_BACK     = 'back';
	public const ACTION_CANCEL   = 'cancel';
	public const ACTION_EVALUATE = 'evaluate';

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$group_path = Settings::get('oz.paths', 'OZ_RESUMABLE_FORM_SERVICE_ROUTE_GROUP_PATH');

		$router->group($group_path, static function (Router $router): void {
			$router->group('/:provider', static function (Router $router): void {
				$router->post('/init', static fn (RouteInfo $ri) => self::standalone($ri, self::ACTION_INIT))
					->name(self::ROUTE_INIT)
					// Opening a session is what costs the server; the steps of one already opened are not
					// limited.
					->rateLimit(static fn (RouteInfo $ri) => new IPRateLimit(
						$ri,
						(int) Settings::get('oz.forms', 'OZ_FORM_RESUME_INIT_IP_RATE'),
						(int) Settings::get('oz.forms', 'OZ_FORM_RESUME_INIT_IP_INTERVAL')
					));

				$router->get('/state', static fn (RouteInfo $ri) => self::standalone($ri, self::ACTION_STATE))
					->name(self::ROUTE_STATE);

				$router->post('/next', static fn (RouteInfo $ri) => self::standalone($ri, self::ACTION_NEXT))
					->name(self::ROUTE_NEXT);

				$router->post('/back', static fn (RouteInfo $ri) => self::standalone($ri, self::ACTION_BACK))
					->name(self::ROUTE_BACK);

				$router->post('/cancel', static fn (RouteInfo $ri) => self::standalone($ri, self::ACTION_CANCEL))
					->name(self::ROUTE_CANCEL);

				$router->post('/evaluate', static fn (RouteInfo $ri) => self::standalone($ri, self::ACTION_EVALUATE))
					->name(self::ROUTE_EVALUATE);
			});
		});
	}

	/**
	 * Handles a standalone `{group-path}/:provider/...` endpoint.
	 *
	 * The provider is resolved by name from `oz.forms.providers`, and the session
	 * is bound to that name: a session opened under one name cannot be driven
	 * under another.
	 *
	 * @param RouteInfo $ri     the current route info
	 * @param string    $action one of the ACTION_* constants
	 *
	 * @throws NotFoundException   when the provider name is not registered
	 * @throws BadRequestException when the provider requires real context
	 */
	public function handleStandalone(RouteInfo $ri, string $action): Response
	{
		$provider_name = $ri->param('provider');
		$class         = Settings::get('oz.forms.providers', $provider_name);

		if (!$class || !\is_a($class, ResumableFormProviderInterface::class, true)) {
			throw new NotFoundException('OZ_FORM_PROVIDER_NOT_FOUND', ['provider' => $provider_name]);
		}

		/** @var class-string<ResumableFormProviderInterface> $class */
		if ($class::requiresRealContext()) {
			throw new BadRequestException('OZ_FORM_PROVIDER_REQUIRES_REAL_CONTEXT', ['provider' => $provider_name]);
		}

		return $this->run(new FormSessionManager($class, $ri, $provider_name), $action);
	}

	/**
	 * Entry point for {@see FormResumeRouteInterceptor}.
	 *
	 * Runs the session lifecycle in the context of the MATCHED route, so the real
	 * route's auth, guards, and middlewares have already been enforced. Sessions
	 * opened here are bound to that route.
	 *
	 * @param class-string<ResumableFormProviderInterface> $providerClass the resolved provider FQCN
	 * @param string                                       $action        one of the ACTION_* constants
	 *
	 * @throws BadRequestException when $action is not a known action token
	 */
	public function handleFromRealContext(string $providerClass, string $action): Response
	{
		$ri = $this->getContext()->getRouteInfo();

		return $this->run(new FormSessionManager($providerClass, $ri), $action);
	}

	/**
	 * Route handler of the standalone endpoints.
	 */
	private static function standalone(RouteInfo $ri, string $action): Response
	{
		return (new self($ri))->handleStandalone($ri, $action);
	}

	/**
	 * Dispatches an action to the state machine and builds the response.
	 *
	 * @throws BadRequestException when $action is not a known action token
	 */
	private function run(FormSessionManager $manager, string $action): Response
	{
		return match ($action) {
			self::ACTION_INIT     => $this->respondStep($manager->init()),
			self::ACTION_STATE    => $this->respondStep($manager->state()),
			self::ACTION_NEXT     => $this->respondStep($manager->next()),
			self::ACTION_BACK     => $this->respondStep($manager->back()),
			self::ACTION_CANCEL   => $this->respondCancel($manager),
			self::ACTION_EVALUATE => $this->respondData($manager->evaluate()),
			default               => throw new BadRequestException(
				'OZ_FORM_RESUME_INVALID_ACTION',
				['action' => $action]
			),
		};
	}

	private function respondStep(FormSessionStep $step): Response
	{
		$this->json()
			->setDone()
			->setData($step->toResponseData())
			->setForm($step->form);

		return $this->respond();
	}

	private function respondCancel(FormSessionManager $manager): Response
	{
		$manager->cancel();

		return $this->respondData(['done' => true]);
	}

	private function respondData(array $data): Response
	{
		$this->json()
			->setDone()
			->setData($data);

		return $this->respond();
	}
}
