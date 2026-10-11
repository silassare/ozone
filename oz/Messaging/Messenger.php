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

use Gobl\DBAL\Exceptions\DBALUniqueViolationException;
use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\GarbageCollector;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Cli\Cron\Cron;
use OZONE\Core\Cli\Cron\Hooks\CronCollect;
use OZONE\Core\Db\OZMessage;
use OZONE\Core\Db\OZMessagesQuery;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\Assets;
use OZONE\Core\FS\Templates;
use OZONE\Core\Hooks\Events\FinishHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Lang\Polyglot;
use OZONE\Core\Messaging\Enums\MessageStatus;
use OZONE\Core\Messaging\Messages\Message;
use OZONE\Core\OZone;
use OZONE\Core\Queue\JobsManager;
use OZONE\Core\Queue\JobState;
use OZONE\Core\Queue\Queue;
use OZONE\Core\Stores\CacheRegistry;
use OZONE\Core\Utils\JSONResult;
use PHPUtils\Events\Event;
use Throwable;

/**
 * Class Messenger.
 *
 * Sends the messages of every channel (`oz.messaging`), OZone's own (email and phone verification,
 * two-factor codes) as much as a project's.
 *
 * A message is recorded in `oz_messages` once per recipient, before anything is attempted, so "was it
 * sent, where, what happened" always has an answer. Then:
 *
 * - **skipped**, and logged, when it has no destination, its channel is disabled, or its transport is
 *   unavailable: never a silent drop, never a failure of the caller either;
 * - **delivered now** when the message asks for it ({@see Message::sync()}) or its channel is not
 *   `async`: a failure is recorded, then thrown;
 * - otherwise **queued**: a job (payload encrypted) on `OZ_MESSAGING_QUEUE`, run right after the
 *   response of the request that queued it, and by the {@see CRON_TASK} task every minute, which
 *   also runs the retries.
 *
 * A channel that is not declared, or whose transport is not a {@see Transports\TransportInterface},
 * throws: a configuration error.
 */
final class Messenger implements BootHookReceiverInterface
{
	/**
	 * The cron task that sends what is queued.
	 */
	public const CRON_TASK = 'oz:messages';

	/**
	 * The collector deleting the old records (`GarbageCollector`, the hourly `oz:gc` task).
	 */
	public const GC_NAME = 'oz:messages';

	/**
	 * Why a message was not attempted (`oz_messages.msg_last_error`).
	 */
	public const SKIPPED_NO_DESTINATION       = 'no destination';
	public const SKIPPED_CHANNEL_DISABLED     = 'channel disabled';
	public const SKIPPED_TRANSPORT_UNAVAILABLE = 'transport unavailable';

	/**
	 * The length `oz_messages.msg_last_error` keeps.
	 */
	private const ERROR_MAX = 512;

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function boot(): void
	{
		JobsManager::registerWorker(MessageWorker::class);

		// Declared now, so `oz jobs run --queue=` and `oz jobs work --queue=` accept it.
		Queue::get(self::queue());

		FinishHook::listen(static function (): void {
			self::afterResponse();
		}, Event::RUN_LAST);

		GarbageCollector::register(self::GC_NAME, self::gc(...));

		CronCollect::listen(static function (): void {
			Cron::call(static function (JSONResult $result): void {
				$result->setDone()->setData(['sent' => self::runQueue()]);
			}, self::CRON_TASK, 'Send the queued messages and retry the failed ones.')->everyMinute();
		});
	}

	/**
	 * The queue of the messages delivered by a job.
	 */
	public static function queue(): string
	{
		return (string) Settings::get('oz.messaging', 'OZ_MESSAGING_QUEUE');
	}

	/**
	 * Records a message for each recipient, then skips, delivers or queues it.
	 *
	 * @return list<OZMessage> the records written; a message already sent once is not among them
	 *
	 * @throws Throwable when the channel is not declared, or a synchronous delivery fails
	 */
	public static function send(Message $message): array
	{
		$channel    = Channel::get($message->getChannel());
		$recipients = $message->getRecipients();
		$once_key   = $message->getOnceKey();

		if (null !== $once_key && \count($recipients) > 1) {
			throw new RuntimeException(\sprintf(
				'A message sent once has one recipient: "%s" has %d.',
				$message->getTemplate(),
				\count($recipients)
			));
		}

		if (null !== $once_key && self::alreadySent($channel->name, $message->getTemplate(), $once_key)) {
			return [];
		}

		$transport = $channel->transport();
		$available = $channel->enabled && $transport->isAvailable();
		$locale    = $message->getLocale() ?? self::currentLocale();
		$records   = [];

		foreach ($recipients as $recipient) {
			$destination = self::destinationOf($recipient, $channel);
			$record      = new OZMessage();

			$record->setChannel($channel->name)
				->setTemplate($message->getTemplate())
				->setDestination($destination)
				->setLocale($locale)
				->setOnceKey($once_key);

			$skipped = match (true) {
				'' === $destination   => self::SKIPPED_NO_DESTINATION,
				!$channel->enabled    => self::SKIPPED_CHANNEL_DISABLED,
				!$available           => self::SKIPPED_TRANSPORT_UNAVAILABLE,
				default               => null,
			};

			if (null !== $skipped) {
				$record->setStatus(MessageStatus::SKIPPED)
					->setLastError($skipped);
			}

			try {
				$record->save();
			} catch (DBALUniqueViolationException) {
				// Sent once, by another request between the check above and this insert.
				continue;
			}

			$records[] = $record;

			if (null !== $skipped) {
				self::logSkipped($record, $skipped);

				continue;
			}

			if ($message->isSync() || !$channel->async) {
				self::attempt($record, $message->getData(), $message->getSubject(), true);

				continue;
			}

			$job = Queue::get(self::queue())
				->push(new MessageWorker((string) $record->getID(), $message->getData(), $message->getSubject()))
				->setRetryMax($channel->retry_max)
				->setRetryDelay($channel->retry_delay)
				->encrypted()
				->dispatch();

			$record->setJobRef($job->getRef())->save();

			self::runAfterResponse($job->getRef());
		}

		return $records;
	}

	/**
	 * Attempts to deliver a recorded message, and records what happened.
	 *
	 * A channel disabled, or a transport that became unavailable, since the message was queued skips
	 * it: switching a channel off stops what is waiting on it.
	 *
	 * @param array<string, mixed> $data    the data the templates are rendered with
	 * @param null|string          $subject the subject, a lang key or a text
	 * @param bool                 $last    whether a failure is final (the message is then failed)
	 *
	 * @throws Throwable when the delivery fails, once recorded
	 */
	public static function attempt(OZMessage $record, array $data, ?string $subject, bool $last): void
	{
		$channel   = Channel::get($record->getChannel());
		$transport = $channel->transport();
		$skipped   = match (true) {
			!$channel->enabled            => self::SKIPPED_CHANNEL_DISABLED,
			!$transport->isAvailable()    => self::SKIPPED_TRANSPORT_UNAVAILABLE,
			default                       => null,
		};

		if (null !== $skipped) {
			$record->setStatus(MessageStatus::SKIPPED)
				->setLastError($skipped)
				->save();

			self::logSkipped($record, $skipped);

			return;
		}

		$record->setAttempts($record->getAttempts() + 1);

		try {
			$transport->deliver($record, self::render($record, $channel, $data, $subject));
		} catch (Throwable $t) {
			$record->setLastError(\mb_substr($t->getMessage(), 0, self::ERROR_MAX));

			if ($last) {
				$record->setStatus(MessageStatus::FAILED);
			}

			$record->save();

			throw $t;
		}

		$record->setStatus(MessageStatus::SENT)
			->setLastError(null)
			->setSentAT(\time())
			->save();
	}

	/**
	 * Renders a recorded message for its channel and locale (see {@see Message} for the templates
	 * looked for).
	 *
	 * @param array<string, mixed> $data
	 */
	public static function render(OZMessage $record, Channel $channel, array $data, ?string $subject): RenderedMessage
	{
		$template = $record->getTemplate();
		$locale   = $record->getLocale();
		$text     = self::firstTemplate(self::candidates($template, $channel->name, $locale, ''));

		if (null === $text) {
			throw new RuntimeException(\sprintf(
				'No template for the message "%s" on the channel "%s": "%s.blate" is the one every message needs.',
				$template,
				$channel->name,
				$template
			));
		}

		$html = $channel->html
			? self::firstTemplate(self::candidates($template, $channel->name, $locale, '.html'))
			: null;

		$body = Templates::compile($text, $data);

		return new RenderedMessage(
			$channel->gsm7 ? Gsm7::fold($body) : $body,
			null === $html ? null : Templates::compile($html, $data),
			null === $subject ? null : Polyglot::translate($subject, $data, $locale)
		);
	}

	/**
	 * Runs the queued messages that are due.
	 *
	 * @return int how many were run
	 */
	public static function runQueue(): int
	{
		return JobsManager::run(Queue::DEFAULT_STORE, self::queue());
	}

	/**
	 * The enabled channels that send nothing, and why: their transport is the log transport, or is
	 * unavailable. What a release check reads (`oz doctor check` fails on it in production).
	 *
	 * @return array<string, string> the reason, by channel name
	 */
	public static function channelsNotSending(): array
	{
		$out = [];

		foreach (Channel::all() as $name => $channel) {
			if (!$channel->enabled) {
				continue;
			}

			if ($channel->usesLogTransport()) {
				$out[$name] = 'its transport writes to the log';
			} elseif (!$channel->transport()->isAvailable()) {
				$out[$name] = 'its transport is unavailable';
			}
		}

		return $out;
	}

	/**
	 * Deletes the settled records (sent, failed, skipped) older than their retention, counted from when
	 * they settled: `OZ_MESSAGING_RETENTION` days, and `OZ_MESSAGING_ONCE_RETENTION` days for the ones sent
	 * once, whose record is what keeps them from being sent again. A null retention keeps them. A queued
	 * record is never deleted.
	 */
	public static function gc(): void
	{
		if (!OZone::hasDbInstalled()) {
			return;
		}

		$settled = [MessageStatus::SENT, MessageStatus::FAILED, MessageStatus::SKIPPED];

		foreach ([false, true] as $once) {
			$days = Settings::get('oz.messaging', $once ? 'OZ_MESSAGING_ONCE_RETENTION' : 'OZ_MESSAGING_RETENTION');

			if (null === $days) {
				continue;
			}

			$qb = (new OZMessagesQuery())
				->whereStatusIsIn($settled)
				->whereUpdatedAtIsLt((string) (\time() - \max(0, (int) $days) * 86400));

			($once ? $qb->whereOnceKeyIsNotNull() : $qb->whereOnceKeyIsNull())
				->delete()
				->execute();
		}
	}

	/**
	 * Whether a message of this template, on this channel, with this once key is recorded.
	 */
	private static function alreadySent(string $channel, string $template, string $once_key): bool
	{
		return 0 < (new OZMessagesQuery())
			->whereChannelIs($channel)
			->whereTemplateIs($template)
			->whereOnceKeyIs($once_key)
			->find()
			->getTotal();
	}

	/**
	 * Where a recipient is reached on a channel: the recipient itself, or the identifier of a user the
	 * channel names. Empty when there is none.
	 */
	private static function destinationOf(AuthUserInterface|string $recipient, Channel $channel): string
	{
		if (\is_string($recipient)) {
			return \trim($recipient);
		}

		$identifiers = $recipient->getAuthIdentifiers();

		return \trim((string) ($identifiers[$channel->identifier] ?? ''));
	}

	/**
	 * The language of the current request, or the default one outside a request.
	 */
	private static function currentLocale(): string
	{
		if (!OZone::isCliMode() && Context::hasRoot()) {
			return Polyglot::getLanguage(Context::current());
		}

		return Polyglot::getDefaultLanguage();
	}

	/**
	 * The template paths to look for, most specific first.
	 *
	 * @return list<string>
	 */
	private static function candidates(string $template, string $channel, ?string $locale, string $part): array
	{
		$names = [];

		if (null !== $locale && '' !== $locale) {
			$names[] = \sprintf('%s.%s.%s', $template, $locale, $channel);
		}

		$names[] = \sprintf('%s.%s', $template, $channel);

		if (null !== $locale && '' !== $locale) {
			$names[] = \sprintf('%s.%s', $template, $locale);
		}

		$names[] = $template;

		return \array_map(static fn (string $name): string => $name . $part . '.blate', $names);
	}

	/**
	 * The first of these templates that exists, or null.
	 *
	 * @param list<string> $paths
	 */
	private static function firstTemplate(array $paths): ?string
	{
		foreach ($paths as $path) {
			if (false !== Assets::localize($path)) {
				return $path;
			}
		}

		return null;
	}

	private static function logSkipped(OZMessage $record, string $reason): void
	{
		$log = \sprintf(
			'Message %s ("%s") skipped on the channel "%s": %s.',
			$record->getID(),
			$record->getTemplate(),
			$record->getChannel(),
			$reason
		);

		// A user without an address is ordinary; a channel that cannot send is not.
		if (self::SKIPPED_NO_DESTINATION === $reason) {
			oz_logger()->warning($log);
		} else {
			oz_logger()->error($log);
		}
	}

	/**
	 * Has the job run once the response of the current request is sent.
	 */
	private static function runAfterResponse(string $job_ref): void
	{
		$runtime = CacheRegistry::runtime(self::class);
		$refs    = (array) $runtime->get('refs', []);

		$refs[] = $job_ref;

		$runtime->set('refs', $refs);
	}

	/**
	 * Runs the jobs the request queued, once its response is sent.
	 */
	private static function afterResponse(): void
	{
		$runtime = CacheRegistry::runtime(self::class);
		$refs    = (array) $runtime->get('refs', []);

		if (empty($refs)) {
			return;
		}

		$runtime->set('refs', []);

		foreach ($refs as $ref) {
			try {
				$job = JobsManager::getStore(Queue::DEFAULT_STORE)->get((string) $ref);

				if (null !== $job && JobState::PENDING === $job->getState()) {
					JobsManager::runJob($job);
				}
			} catch (Throwable $t) {
				// The response is sent: the record and the log say what happened, the cron task retries.
				oz_logger()->error($t);
			}
		}
	}
}
