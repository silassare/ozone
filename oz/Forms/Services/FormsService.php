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

namespace OZONE\Core\Forms\Services;

use Override;
use OZONE\Core\App\Service;
use OZONE\Core\Exceptions\BadRequestException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormRegistry;
use OZONE\Core\Http\Response;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Class FormsService.
 *
 * Exposes forms registered via {@see Form::key()} as JSON at a
 * stable URL so clients can fetch a form definition independently of the route
 * that uses it.
 */
final class FormsService extends Service
{
	public const FORM_KEY_PARAM = 'form_key';

	public const STEP_NAME_PARAM = 'step_name';

	public const GET_FORM_ROUTE = 'oz:forms.get';

	public const VALIDATE_ROOT_STEP_ROUTE = 'oz:forms.step.root';

	public const VALIDATE_NAMED_STEP_ROUTE = 'oz:forms.step.named';

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$router->get('/forms/:' . self::FORM_KEY_PARAM, static function (RouteInfo $ri) {
			return (new self($ri))->actionGetForm($ri->param(self::FORM_KEY_PARAM, ''));
		})->name(self::GET_FORM_ROUTE);

		// Validate root-level fields only and return the first enabled step form.
		$router->post('/forms/:' . self::FORM_KEY_PARAM . '/step', static function (RouteInfo $ri) {
			return (new self($ri))->actionGetNextStep($ri, $ri->param(self::FORM_KEY_PARAM, ''), null);
		})->name(self::VALIDATE_ROOT_STEP_ROUTE);

		// Validate root-level fields + all steps up to and including the named step,
		// then return the next enabled step form (or signal completion).
		$router->post('/forms/:' . self::FORM_KEY_PARAM . '/step/:' . self::STEP_NAME_PARAM, static function (RouteInfo $ri) {
			return (new self($ri))->actionGetNextStep(
				$ri,
				$ri->param(self::FORM_KEY_PARAM, ''),
				$ri->param(self::STEP_NAME_PARAM, '')
			);
		})->name(self::VALIDATE_NAMED_STEP_ROUTE);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function apiDoc(ApiDoc $doc): void
	{
		$tag = $doc->addTag('Forms', 'Form discovery and step-by-step validation endpoints.');

		$doc->addOperationFromRoute(
			self::GET_FORM_ROUTE,
			'GET',
			'Get Form Definition',
			[
				$doc->success([
					'form' => $doc->object([
						'key'          => $doc->string('The form key.'),
						'version'      => $doc->string('Structural fingerprint of the form (16-char hex). Changes when the form structure changes.'),
						'prefix'       => $doc->string('Optional field name prefix.', ['nullable' => true]),
						'method'       => $doc->string('HTTP method the form should be submitted with.'),
						'resume_scope' => $doc->string('Resume scope strategy, or null if resume is disabled.', ['nullable' => true]),
						'resume_ttl'   => $doc->integer('Resume cache TTL in seconds, or null if resume is disabled.', ['nullable' => true]),
					]),
				]),
				$doc->response(404, 'Form not found.', []),
			],
			[
				'tags'        => [$tag->name],
				'operationId' => 'Forms.getForm',
				'description' => 'Returns the serialized form definition for a form registered via Form::key(). Forms must explicitly opt in by calling key() on the Form instance.',
			]
		);

		$next_step_success = [
			$doc->success([
				'done'           => $doc->boolean('True when there are no more steps after the current one.'),
				'next_step_name' => $doc->string('Name of the next step, or null when done.', ['nullable' => true]),
				'next_step'      => $doc->object([], ['description' => 'Serialized form definition for the next step, or null when done.', 'nullable' => true]),
			]),
			$doc->response(422, 'Root-field validation failed, or named step is disabled.', []),
			$doc->response(404, 'Form or step not found.', []),
		];

		$doc->addOperationFromRoute(
			self::VALIDATE_ROOT_STEP_ROUTE,
			'POST',
			'Validate Root Fields and Get First Step',
			$next_step_success,
			[
				'tags'        => [$tag->name],
				'operationId' => 'Forms.validateRootStep',
				'description' => 'Validates the root-level fields of the form (no step sub-forms) and returns the first enabled step form definition. Send all root field values in the request body.',
			]
		);

		$doc->addOperationFromRoute(
			self::VALIDATE_NAMED_STEP_ROUTE,
			'POST',
			'Validate Step and Get Next Step',
			$next_step_success,
			[
				'tags'        => [$tag->name],
				'operationId' => 'Forms.validateNamedStep',
				'description' => 'Validates the root fields plus all steps up to and including the named step, then returns the next enabled step form definition. Send all accumulated field values (root + all completed steps) in the request body.',
			]
		);
	}

	/**
	 * Returns the serialized form definition for the given key.
	 *
	 * @param string $key The form key registered via {@see Form::key()}
	 *
	 * @return Response
	 *
	 * @throws NotFoundException when no form is registered under the given key
	 */
	private function actionGetForm(string $key): Response
	{
		$form = FormRegistry::get($key);

		if (!$form) {
			throw new NotFoundException('OZ_FORM_NOT_FOUND');
		}

		$this->json()->setDone()->setDataKey('form', $form);

		return $this->respond();
	}

	/**
	 * Validates root fields (and optionally up to a named step) and returns the next enabled step.
	 *
	 * When `$current_step_name` is null, only root fields are validated and the first enabled step
	 * form is returned. When a step name is given, all steps up to and including that step are
	 * validated (so dynamic step factories have fully accumulated data), and the next enabled step
	 * form is returned. Returns `done: true` when no further steps exist.
	 *
	 * The client must send all accumulated field values (root + all previously completed steps)
	 * in the request body. Fields belonging to steps not yet completed are ignored.
	 *
	 * @param RouteInfo   $ri                The current route info (used to read request body)
	 * @param string      $form_key          The form key registered via {@see Form::key()}
	 * @param null|string $current_step_name The step the client just completed, or null for root
	 *
	 * @return Response
	 *
	 * @throws NotFoundException   when the form or step name is not found
	 * @throws BadRequestException when the named step's condition is not satisfied
	 */
	private function actionGetNextStep(RouteInfo $ri, string $form_key, ?string $current_step_name): Response
	{
		$form = FormRegistry::get($form_key);

		if (!$form) {
			throw new NotFoundException('OZ_FORM_NOT_FOUND');
		}

		$unsafe_fd = $ri->getUnsafeFormData();

		// Validate root fields only: this gives us clean values that dynamic step
		// factories may need, without triggering step validation yet.
		$cleaned_fd = $form->validate($unsafe_fd, null, skip_steps: true);

		$found = null === $current_step_name; // for root case we start looking immediately

		foreach ($form->t_steps as $step) {
			$step_form = $step->build($cleaned_fd);

			if ($found) {
				// Already past the current step — return the first enabled step we find.
				if (null !== $step_form) {
					return $this->buildNextStepResponse(false, $step->getName(), $step_form);
				}

				// Disabled step — keep looking.
				continue;
			}

			if ($step->getName() === $current_step_name) {
				if (null === $step_form) {
					// The client targeted a step whose condition is not satisfied.
					throw new BadRequestException('OZ_FORM_STEP_DISABLED');
				}

				// Validate the current step (including its own nested steps).
				$step_form->validate($unsafe_fd, $cleaned_fd);

				$found = true;
			} else {
				// A step that precedes the current one: validate it so that
				// $cleaned_fd accumulates outputs needed by later dynamic factories.
				if (null !== $step_form) {
					$step_form->validate($unsafe_fd, $cleaned_fd);
				}
			}
		}

		if (!$found) {
			throw new NotFoundException('OZ_FORM_STEP_NOT_FOUND');
		}

		return $this->buildNextStepResponse(true, null, null);
	}

	/**
	 * Builds the JSON response for `actionGetNextStep`.
	 *
	 * @param bool        $done           True when all steps have been completed
	 * @param null|string $next_step_name Name of the next step (null when done)
	 * @param null|Form   $next_step      Next step form definition (null when done)
	 *
	 * @return Response
	 */
	private function buildNextStepResponse(bool $done, ?string $next_step_name, ?Form $next_step): Response
	{
		$this->json()->setDone()->setData([
			'done'           => $done,
			'next_step_name' => $next_step_name,
			'next_step'      => $next_step,
		]);

		return $this->respond();
	}
}
