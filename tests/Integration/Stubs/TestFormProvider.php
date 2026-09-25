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

use Gobl\DBAL\Types\TypeInt;
use Gobl\DBAL\Types\TypeString;
use Override;
use OZONE\Core\Forms\AsyncValue;
use OZONE\Core\Forms\Fieldset;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormDataClean;
use OZONE\Core\Forms\Resume\AbstractResumableFormProvider;
use OZONE\Core\Forms\Resume\FormResumeProgress;
use OZONE\Core\Forms\RuleSet;
use OZONE\Core\Forms\TypesSwitcher;
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
 *      + an expect rule on a value with a preview (sent with the step)
 *      + 'size', whose switcher branch holds a secret (a string for a big color, else an int)
 *   2: 'notes' (optional string) + server-only expect rule (current-step)
 *      + server-only ensure rules (notes, and cross-step color)
 *      + a fieldset with server-only expect and ensure rules of its own
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
	public function nextStep(FormDataClean $cleaned_fd, FormResumeProgress $progress): ?Form
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
				// A secret value withholds this condition: only its ref is sent.
				$f->string('hint');
				$f->field('hint')->if()->neq('name', AsyncValue::secret(static fn (): string => 'skip'));
				// A value with a preview: a client gets it with the step and checks the rule itself.
				$f->expect()->neq('color', AsyncValue::public(
					static fn (): string => 'black',
					static fn (): string => 'black'
				), 'NO_BLACK');
				$f->switcher('size')->configureType(static fn (TypesSwitcher $s) => $s
					->when(
						static fn (RuleSet $rs) => $rs->eq('color', AsyncValue::secret(static fn (): string => 'big')),
						new TypeString()
					)
					->otherwise(new TypeInt()));

				return $f;
			})(),
			2 => (static function (): Form {
				$f = new Form();
				$f->string('notes');
				// Server-only expect rule. expect() reads the UNSAFE side, which for a
				// wizard step holds only that step's own raw payload -- so it can only
				// reference fields of the current step: notes must not be 'forbidden-notes'.
				$f->expect()->neq('notes', AsyncValue::secret(static fn (): string => 'forbidden-notes'));
				// Server-only ensure rules. ensure() reads the CLEANED side, which is the
				// accumulated store, so cross-step references belong here.
				// [0] notes must not be 'bad-notes'.
				$f->ensure()->neq('notes', AsyncValue::secret(static fn (): string => 'bad-notes'));
				// [1] cross-step: the color picked on step 1 must not be 'forbidden'.
				$f->ensure()->neq('color', AsyncValue::secret(static fn (): string => 'forbidden'));
				// Fieldset with a server-only if condition: shown only when wish != 'skip-details'.
				$fs = $f->fieldset('extra_details', static function (Fieldset $fs): void {
					$fs->string('detail_note');
					// Field inside fieldset with server-only if: shown only when wish != 'skip-detail'.
					$fs->string('conditional_detail');
					$fs->field('conditional_detail')
						->if()->neq('wish', AsyncValue::secret(static fn (): string => 'skip-detail'));
					// The fieldset's own rules, server-only: the raw note, then the cleaned one.
					$fs->expect()
						->neq('extra_details.detail_note', AsyncValue::secret(static fn (): string => 'raw-bad'));
					$fs->ensure()
						->neq('extra_details.detail_note', AsyncValue::secret(static fn (): string => 'bad'));
				});
				$fs->if()->neq('wish', AsyncValue::secret(static fn (): string => 'skip-details'));

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
