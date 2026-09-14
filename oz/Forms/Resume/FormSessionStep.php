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

namespace OZONE\Core\Forms\Resume;

use OZONE\Core\Forms\Form;

/**
 * Class FormSessionStep.
 *
 * Outcome of a session transition (init, state, next, back): the form the
 * client should fill next and the session metadata that goes with it.
 */
final class FormSessionStep
{
	/**
	 * FormSessionStep constructor.
	 *
	 * @param string                                  $resume_ref the session reference
	 * @param null|Form                               $form       the form to fill next; null when done
	 * @param bool                                    $done       true when every step has been submitted
	 * @param null|int                                $expires_at hard deadline UNIX timestamp, or null
	 * @param null|array{step: int, total_steps: int} $progress   set when the provider declares `totalSteps()`
	 */
	public function __construct(
		public readonly string $resume_ref,
		public readonly ?Form $form,
		public readonly bool $done,
		public readonly ?int $expires_at,
		public readonly ?array $progress,
	) {}

	/**
	 * Response data, excluding the form (sent separately via `setForm()`).
	 *
	 * @return array
	 */
	public function toResponseData(): array
	{
		$data = [
			'resume_ref' => $this->resume_ref,
			'done'       => $this->done,
			'expires_at' => $this->expires_at,
		];

		if (null !== $this->progress) {
			$data['progress'] = $this->progress;
		}

		return $data;
	}
}
