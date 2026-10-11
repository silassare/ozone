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

namespace OZONE\Core\Messaging;

use Override;
use OZONE\Core\Db\OZMessagesQuery;
use OZONE\Core\Messaging\Enums\MessageStatus;
use OZONE\Core\Queue\Interfaces\JobContractInterface;
use OZONE\Core\Queue\Interfaces\WorkerInterface;
use OZONE\Core\Utils\JSONResult;

/**
 * Class MessageWorker.
 *
 * Delivers one queued message ({@see Messenger::send()}). A failed attempt throws, so the queue
 * retries the job (the channel's `retry_max` and `retry_delay`); the last one marks the message failed.
 *
 * The payload is `['message_id' => string, 'data' => array, 'subject' => ?string]`, encrypted in the
 * job store: the data may hold a code or a link.
 */
final class MessageWorker implements WorkerInterface
{
	private JSONResult $result;

	/**
	 * MessageWorker constructor.
	 *
	 * @param string               $message_id the `oz_messages` record
	 * @param array<string, mixed> $data       the data the templates are rendered with
	 * @param null|string          $subject    the subject, a lang key or a text
	 */
	public function __construct(
		private readonly string $message_id,
		private readonly array $data,
		private readonly ?string $subject,
	) {
		$this->result = new JSONResult();
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function getName(): string
	{
		return self::class;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function isAsync(): bool
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function work(JobContractInterface $job_contract): static
	{
		$record = (new OZMessagesQuery())
			->whereIdIs($this->message_id)
			->find()
			->fetchClass();

		// Gone, or settled by another run: nothing to send again.
		if (null === $record || MessageStatus::QUEUED !== $record->getStatus()) {
			$this->result->setDone()->setData(['message_id' => $this->message_id, 'skipped' => true]);

			return $this;
		}

		// The try count includes this attempt (JobsManager counts it before work()).
		$last = $job_contract->getTryCount() >= $job_contract->getRetryMax();

		Messenger::attempt($record, $this->data, $this->subject, $last);

		$this->result->setDone()->setData([
			'message_id' => $this->message_id,
			'status'     => $record->getStatus()->value,
		]);

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function fromPayload(array $payload): static
	{
		$subject = $payload['subject'] ?? null;

		return new self(
			(string) $payload['message_id'],
			(array) ($payload['data'] ?? []),
			null === $subject ? null : (string) $subject
		);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getPayload(): array
	{
		return [
			'message_id' => $this->message_id,
			'data'       => $this->data,
			'subject'    => $this->subject,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getResult(): JSONResult
	{
		return $this->result;
	}
}
