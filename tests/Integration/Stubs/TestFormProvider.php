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

namespace __PLH_NAMESPACE__;

use Override;
use OZONE\Core\Forms\AbstractResumableFormProvider;
use OZONE\Core\Forms\DynamicValue;
use OZONE\Core\Forms\Fieldset;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Forms\FormResumeProgress;
use OZONE\Core\Http\Enums\RequestScope;

/**
 * Three-step resumable form provider used by integration tests.
 *
 * - requiresRealContext() = false (usable from standalone /form/* endpoints)
 * - resumeScope() = HOST (scope tied to host, no cookie needed in tests)
 * - initForm() returns a form with a required 'wish' field
 * - Steps:
 *   0: 'name' (required string)
 *   1: 'color' (required string) + 'hint' (optional, server-only visibility condition)
 *   2: 'notes' (optional string) + server-only expect rule
 * - totalSteps() = 3
 * - isReversible() = true
 */
final class TestFormProvider extends AbstractResumableFormProvider
{
	public const PROVIDER_NAME = 'test-wizard';

	#[Override]
	public static function getName(): string
	{
		return self::PROVIDER_NAME;
	}

	#[Override]
	public static function requiresRealContext(): bool
	{
		return false;
	}

	#[Override]
	public static function initForm(): ?Form
	{
		$form = new Form();
		$form->string('wish', true);

		return $form;
	}

	#[Override]
	public function resumeScope(): RequestScope
	{
		// HOST scope: all requests from the same host share the same scope_id.
		// This avoids session-cookie management in tests.
		return RequestScope::HOST;
	}

	#[Override]
	public function nextStep(FormData $cleaned_form, FormResumeProgress $progress): ?Form
	{
		return match ($progress->getStepIndex()) {
			0 => (static function (): Form {
				$f = new Form();
				$f->string('name', true);

				return $f;
			})(),
			1 => (static function (): Form {
				$f = new Form();
				$f->string('color', true);
				// 'hint' is server-conditionally visible: shown only when name != 'skip'.
				// DynamicValue makes this condition server-only (not sent to client).
				$f->string('hint');
				$f->field('hint')->if()->neq('name', new DynamicValue(static fn (FormData $fd): string => 'skip'));

				return $f;
			})(),
			2 => (static function (): Form {
				$f = new Form();
				$f->string('notes');
				// Server-only expect rule: color must not be 'forbidden'.
				$f->expect()->neq('color', new DynamicValue(static fn (FormData $fd): string => 'forbidden'));
				// Server-only ensure rule: notes must not be 'bad-notes'.
				$f->ensure()->neq('notes', new DynamicValue(static fn (FormData $fd): string => 'bad-notes'));
				// Fieldset with a server-only if condition: shown only when wish != 'skip-details'.
				$fs = $f->fieldset('extra_details', static function (Fieldset $fs): void {
					$fs->string('detail_note');
					// Field inside fieldset with server-only if: shown only when wish != 'skip-detail'.
					$fs->string('conditional_detail');
					$fs->field('conditional_detail')
						->if()->neq('wish', new DynamicValue(static fn (FormData $fd): string => 'skip-detail'));
				});
				$fs->if()->neq('wish', new DynamicValue(static fn (FormData $fd): string => 'skip-details'));

				return $f;
			})(),
			default => null,
		};
	}

	#[Override]
	public function totalSteps(): ?int
	{
		return 3;
	}

	#[Override]
	public function isReversible(): bool
	{
		return true;
	}
}
