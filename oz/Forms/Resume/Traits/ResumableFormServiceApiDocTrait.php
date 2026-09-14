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

namespace OZONE\Core\Forms\Resume\Traits;

use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Forms\Resume\Services\ResumableFormService;
use OZONE\Core\REST\ApiDoc;

/**
 * Trait ResumableFormServiceApiDocTrait.
 *
 * OpenAPI documentation for the {@see ResumableFormService} endpoints.
 */
trait ResumableFormServiceApiDocTrait
{
	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function apiDoc(ApiDoc $doc): void
	{
		$tag = $doc->addTag('Resumable Forms', 'Multi-step, session-resumable form submission endpoints.');

		$resume_ref_header = Settings::get('oz.request', 'OZ_FORM_RESUME_REF_HEADER_NAME');

		$tag_desc = <<<DESC
Multi-step form session endpoints.

Providers are registered in `oz.forms.providers`. Each provider controls
the steps, their forms, reversibility, and session lifetime.

After calling `init`, include the session reference from `data.resume_ref`
in the `{$resume_ref_header}` header on every subsequent request.

Providers that set `requiresRealContext() = true` are not accessible here;
they must be driven through the route they are attached to (see **OZone Form System**).
DESC;
		$tag->description = $tag_desc;

		$provider_param_desc = 'Provider name as registered in `oz.forms.providers`. '
			. 'Providers with `requiresRealContext() = true` are not available here.';

		$resume_ref_param = $doc->parameter(
			$resume_ref_header,
			$doc->string('The session reference from `data.resume_ref`.'),
			'Session reference from `data.resume_ref`. Required on all endpoints except `init`.',
			'header'
		);

		// A fresh schema per operation.
		$expires_at = static fn () => $doc->integer(
			'UNIX timestamp when the session expires, or `null`.',
			['nullable' => true]
		);

		// --- init ---
		$op_init = $doc->addOperationFromRoute(ResumableFormService::ROUTE_INIT, 'POST', 'Init Session', [
			$doc->success([
				'resume_ref' => $doc->string(
					'Opaque 32-char session reference. Send it back on every later call in the `'
						. $resume_ref_header . '` header.'
				),
				'done'       => $doc->boolean('`true` when the provider has no steps and is already complete.'),
				'expires_at' => $expires_at(),
				'form'       => $doc->object([], [
					'description' => 'The first form to fill (the init form when the provider declares one), '
						. 'or `null` when immediately done.',
					'nullable'    => true,
				]),
				'progress'   => $doc->object([
					'step'        => $doc->integer('Zero-based current step index.'),
					'total_steps' => $doc->integer('Total number of steps declared by the provider.'),
				], [
					'description' => 'Step progress, present when the provider declares `totalSteps()`.',
					'nullable'    => true,
				]),
			], 'Session started. Contains the `resume_ref` and the first form to fill.'),
			$doc->error([], 'Too many requests (rate limit: 30 per hour per IP).', 'OZ_RATE_LIMIT_EXCEEDED', 429),
			$doc->error([], 'Provider not found or requires real context.', 'OZ_FORM_PROVIDER_NOT_FOUND', 200),
			$doc->error([], 'Session cannot start yet (`notBefore()`).', 'OZ_FORM_SESSION_NOT_YET_ACTIVE', 200),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Form.init',
			'description' => 'Start a new form session. Returns `resume_ref` and the first form. '
				. 'Rate limited to 30 requests per hour per IP.',
			'parameters'  => [
				$doc->parameter('provider', $doc->string($provider_param_desc), $provider_param_desc, 'path'),
			],
		]);
		$op_init->requestBody = $doc->requestBody([
			'application/json' => $doc->json($doc->object([], [
				'description'          => 'Optional provider-specific init payload '
					. '(only required when the provider declares an `initForm()`).',
				'additionalProperties' => true,
			])),
		]);

		// --- state ---
		$doc->addOperationFromRoute(ResumableFormService::ROUTE_STATE, 'GET', 'Get Session State', [
			$doc->success([
				'resume_ref' => $doc->string('The current session reference.'),
				'done'       => $doc->boolean('`true` when the session is already complete.'),
				'expires_at' => $expires_at(),
				'form'       => $doc->object([], [
					'description' => 'The form for the current step, or `null` when done.',
					'nullable'    => true,
				]),
				'progress'   => $doc->object([
					'step'        => $doc->integer('Zero-based current step index.'),
					'total_steps' => $doc->integer('Total number of steps.'),
				], [
					'description' => 'Step progress, present when the provider declares `totalSteps()`.',
					'nullable'    => true,
				]),
			], 'Current session state and form.'),
			$doc->error([], 'Session not found or expired.', 'OZ_FORM_SESSION_NOT_FOUND', 200),
			$doc->error([], 'Session belongs to a different caller.', 'OZ_FORM_SESSION_ACCESS_DENIED', 200),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Form.state',
			'description' => 'Re-fetch the current step form without advancing the session.',
			'parameters'  => [
				$doc->parameter('provider', $doc->string($provider_param_desc), $provider_param_desc, 'path'),
				$resume_ref_param,
			],
		]);

		// --- next ---
		$op_next = $doc->addOperationFromRoute(ResumableFormService::ROUTE_NEXT, 'POST', 'Submit Step', [
			$doc->success([
				'resume_ref' => $doc->string('The current session reference.'),
				'done'       => $doc->boolean('`true` when the submitted step was the last one.'),
				'expires_at' => $expires_at(),
				'form'       => $doc->object([], [
					'description' => 'The next step form, or `null` when all steps are done.',
					'nullable'    => true,
				]),
				'progress'   => $doc->object([
					'step'        => $doc->integer('Zero-based step index of the newly active step.'),
					'total_steps' => $doc->integer('Total number of steps.'),
				], ['description' => 'Step progress block (present when `totalSteps()` is set).', 'nullable' => true]),
			], 'Step accepted. Contains the next form (or `done: true` on the last step).'),
			$doc->error([], 'Session already complete.', 'OZ_FORM_SESSION_ALREADY_DONE', 200),
			$doc->error([], 'Session not found or expired.', 'OZ_FORM_SESSION_NOT_FOUND', 200),
			$doc->error([], 'Session belongs to a different caller.', 'OZ_FORM_SESSION_ACCESS_DENIED', 200),
			$doc->error([], 'Form validation failed. Check the error details.', 'OZ_ERROR_INVALID_FORM', 200),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Form.next',
			'description' => 'Submit the current step and advance. When `done` is `true` the session is complete.',
			'parameters'  => [
				$doc->parameter('provider', $doc->string($provider_param_desc), $provider_param_desc, 'path'),
				$resume_ref_param,
			],
		]);
		$op_next->requestBody = $doc->requestBody([
			'application/json' => $doc->json($doc->object([], [
				'description'          => 'Form fields for the current step, as defined by the form returned '
					. 'by the previous `init` or `state` response.',
				'additionalProperties' => true,
			])),
		]);

		// --- back ---
		$op_back = $doc->addOperationFromRoute(ResumableFormService::ROUTE_BACK, 'POST', 'Go Back', [
			$doc->success([
				'resume_ref' => $doc->string('The current session reference.'),
				'done'       => $doc->boolean('Always `false` (going back never reaches done state).'),
				'expires_at' => $expires_at(),
				'form'       => $doc->object([], ['description' => 'The form for the restored previous step.']),
				'progress'   => $doc->object([
					'step'        => $doc->integer('Zero-based step index after reverting.'),
					'total_steps' => $doc->integer('Total number of steps.'),
				], ['description' => 'Step progress block (present when `totalSteps()` is set).', 'nullable' => true]),
			], 'Session reverted. Contains the previous step form.'),
			$doc->error([], 'Provider does not support going back.', 'OZ_FORM_SESSION_NOT_REVERSIBLE', 200),
			$doc->error([], 'Already on the first step (no history).', 'OZ_FORM_SESSION_NO_HISTORY', 200),
			$doc->error([], 'Session not found or expired.', 'OZ_FORM_SESSION_NOT_FOUND', 200),
			$doc->error([], 'Session belongs to a different caller.', 'OZ_FORM_SESSION_ACCESS_DENIED', 200),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Form.back',
			'description' => 'Revert to the previous step. Only available when `isReversible()` is `true`.',
			'parameters'  => [
				$doc->parameter('provider', $doc->string($provider_param_desc), $provider_param_desc, 'path'),
				$resume_ref_param,
			],
		]);
		$op_back->requestBody = $doc->requestBody([
			'application/json' => $doc->json($doc->object([], [
				'description'          => 'No body required. An empty JSON object is acceptable.',
				'additionalProperties' => false,
			])),
		]);

		// --- cancel ---
		$op_cancel = $doc->addOperationFromRoute(ResumableFormService::ROUTE_CANCEL, 'POST', 'Cancel Session', [
			$doc->success(['done' => $doc->boolean('Always `true`.', ['default' => true])], 'Session discarded.'),
			$doc->error([], 'Session not found or expired.', 'OZ_FORM_SESSION_NOT_FOUND', 200),
			$doc->error([], 'Session belongs to a different caller.', 'OZ_FORM_SESSION_ACCESS_DENIED', 200),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Form.cancel',
			'description' => 'Discard the session immediately. The `resume_ref` is invalidated. '
				. 'Note: `onAbandon()` is not called here; it only fires on TTL expiry.',
			'parameters'  => [
				$doc->parameter('provider', $doc->string($provider_param_desc), $provider_param_desc, 'path'),
				$resume_ref_param,
			],
		]);
		$op_cancel->requestBody = $doc->requestBody([
			'application/json' => $doc->json($doc->object([], [
				'description'          => 'No body required.',
				'additionalProperties' => false,
			])),
		]);

		// --- evaluate ---
		$message = static fn () => $doc->string(
			'The violation message if the rule fails, or `null` if it passes.',
			['nullable' => true]
		);

		$op_evaluate = $doc->addOperationFromRoute(ResumableFormService::ROUTE_EVALUATE, 'POST', 'Evaluate Step', [
			$doc->success([
				'visibility' => $doc->object([], [
					'description'          => 'Map of `field_ref -> bool` for fields whose `if()` condition uses '
						. 'an `AsyncValue` (server-side only); `true` means the field is shown.',
					'additionalProperties' => $doc->boolean(),
				]),
				'fieldsets'  => $doc->object([], [
					'description'          => 'Map of `fieldset_ref -> bool` for fieldsets whose `if()` condition '
						. 'uses an `AsyncValue`; `true` means the fieldset is shown.',
					'additionalProperties' => $doc->boolean(),
				]),
				'expect'     => $doc->array($doc->object([
					'ref'     => $doc->string(
						'The ref of the server-only rule set, as announced (`{ref, $async: true}`) in `expect`.'
					),
					'passes'  => $doc->boolean('`true` when the server-only expect rule passes with the input.'),
					'message' => $message(),
				]), ['description' => 'Server-only pre-validation (`expect`) rule results.']),
				'ensure'     => $doc->array($doc->object([
					'ref'     => $doc->string('The ref of the server-only `ensure()` rule set.'),
					'passes'  => $doc->boolean('`true` when the server-only ensure rule passes with the input.'),
					'message' => $message(),
				]), ['description' => 'Server-only post-validation (`ensure`) rule results.']),
			], 'Server-side evaluation results.'),
			$doc->error([], 'Session already complete.', 'OZ_FORM_SESSION_ALREADY_DONE', 200),
			$doc->error([], 'Session not found or expired.', 'OZ_FORM_SESSION_NOT_FOUND', 200),
			$doc->error([], 'Session belongs to a different caller.', 'OZ_FORM_SESSION_ACCESS_DENIED', 200),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Form.evaluate',
			'description' => 'Resolve server-only form conditions for the current step without advancing. '
				. 'Fields and fieldsets using `AsyncValue` in their `if()`, `expect()`, or `ensure()` rules '
				. 'need a server round-trip to evaluate. The response maps each such item to a visibility or '
				. 'pass result. '
				. 'Input is cleaned by type, field by field (missing or invalid values are left out), '
				. 'then merged with the accumulated session data.',
			'parameters'  => [
				$doc->parameter('provider', $doc->string($provider_param_desc), $provider_param_desc, 'path'),
				$resume_ref_param,
			],
		]);
		$op_evaluate->requestBody = $doc->requestBody([
			'application/json' => $doc->json($doc->object([], [
				'description'          => 'Field values for the current step, possibly incomplete. Each is '
					. 'cleaned by type (invalid ones are left out) and merged with the accumulated session data.',
				'additionalProperties' => true,
			])),
		]);
	}
}
