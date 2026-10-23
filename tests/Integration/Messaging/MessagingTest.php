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

namespace OZONE\Tests\Integration\Messaging;

use OZONE\Core\Messaging\Transports\LogTransport;
use OZONE\Core\Testing\DbTestConfig;
use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Messaging on a real project and a real server, on every configured database: recorded, skipped,
 * delivered now or after the response, retried by the queue and the cron task, rendered per channel
 * and locale, sent once.
 *
 * Tests of one database share its project and server and run in declaration order.
 *
 * @internal
 *
 * @coversNothing
 */
final class MessagingTest extends TestCase
{
	/** @var array<string, OZTestProject> */
	private static array $projects = [];

	/** @var array<string, array{0: Process, 1: string, 2: int}> */
	private static array $servers = [];

	/** @var array<string, null|string> */
	private static array $dbFiles = [];

	public static function tearDownAfterClass(): void
	{
		foreach (self::$servers as [$server]) {
			$server->stop();
		}

		foreach (self::$projects as $proj) {
			$proj->destroy();
		}

		foreach (self::$dbFiles as $file) {
			if (null !== $file && \is_file($file)) {
				\unlink($file);
			}
		}

		self::$servers  = [];
		self::$projects = [];
		self::$dbFiles  = [];

		parent::tearDownAfterClass();
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testSetup(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('messaging-' . $rdbms, shared: true, fresh: true);
		$ns    = $proj->getNamespace();
		$path  = $proj->getPath();

		self::$projects[$rdbms] = $proj;
		self::$dbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;

		$config = $proj->useDatabase($config);
		$proj->writeFileFromStub('MessagingTestTransport', 'app/MessagingTestTransport.php', [
			'namespace'        => $ns,
			'deliveries_file'  => $path . '/deliveries.jsonl',
			'fail_file'        => $path . '/fail.txt',
			'unavailable_file' => $path . '/unavailable.flag',
		]);
		$proj->writeFileFromStub('MessagingTestRoutesProvider', 'app/MessagingTestRoutesProvider.php', [
			'namespace' => $ns,
		]);
		$proj->setSetting('oz.routes.api', "{$ns}\\MessagingTestRoutesProvider", true);

		$transport = "{$ns}\\MessagingTestTransport";
		$retries   = ['retry_max' => 2, 'retry_delay' => 0];

		// mail, sms and notification keep the options OZone gives them unless overridden here.
		$proj->setSetting('oz.messaging', 'OZ_MESSAGING_CHANNELS', [
			'mail'     => ['transport' => $transport] + $retries,
			'sms'      => ['transport' => $transport, 'gsm7' => true] + $retries,
			'whatsapp' => ['transport' => $transport, 'identifier' => 'phone'] + $retries,
			'off'      => ['transport' => $transport, 'enabled' => false],
			'logged'   => ['transport' => LogTransport::class, 'async' => false],
		]);

		$proj->writeFile('app/templates/test.hello.blate', 'Hello {= name}, en');
		$proj->writeFile('app/templates/test.hello.fr.blate', 'Bonjour {= name}');
		$proj->writeFile('app/templates/test.hello.sms.blate', 'SMS {= name}: Hôtel « ok »');
		$proj->writeFile('app/templates/test.hello.html.blate', '<p>Hello {name}</p>');

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		self::$servers[$rdbms] = $proj->startServer('api');

		self::assertSame([], self::records($config));
	}

	/**
	 * Queued in the request, sent once its response is gone: rendered with the HTML part and the
	 * translated subject a mail channel takes.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testAsyncMailIsSentAfterTheResponse(DbTestConfig $config): void
	{
		$answer = self::send($config, [
			'channel'  => 'mail',
			'template' => 'test.hello',
			'to'       => ['jane@example.com'],
			'data'     => ['name' => 'Jane'],
			'subject'  => 'OZ_AUTH_2FA_TITLE',
			'locale'   => 'en',
		]);

		self::assertCount(1, $answer['records'], \json_encode($answer));
		self::assertSame('queued', $answer['records'][0]['status']);
		self::assertNotEmpty($answer['records'][0]['job_ref']);

		$record = self::waitFor($config, $answer['records'][0]['id'], 'sent');

		self::assertSame(1, $record['attempts']);
		self::assertTrue($record['sent']);

		$delivery = self::delivery($config, $record['id']);

		self::assertSame('jane@example.com', $delivery['destination']);
		self::assertSame('Hello Jane, en', $delivery['text']);
		self::assertSame('<p>Hello Jane</p>', $delivery['html']);
		self::assertSame('Your sign-in code', $delivery['subject']);
	}

	/**
	 * The most specific template wins: the channel's, then the locale's; an SMS is folded to GSM 03.38
	 * and takes no HTML part.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testTemplatesFollowTheChannelAndTheLocale(DbTestConfig $config): void
	{
		$sms = self::send($config, [
			'channel' => 'sms', 'template' => 'test.hello', 'to' => ['+22990000001'],
			'data'    => ['name' => 'Jöhn'], 'locale' => 'fr', 'sync' => true,
		]);

		self::assertSame('sent', $sms['records'][0]['status'], \json_encode($sms));

		$delivery = self::delivery($config, $sms['records'][0]['id']);

		self::assertSame('SMS Jöhn: Hotel " ok "', $delivery['text']);
		self::assertNull($delivery['html']);
		self::assertSame(1, $delivery['segments']);

		$whatsapp = self::send($config, [
			'channel' => 'whatsapp', 'template' => 'test.hello', 'to' => ['+22990000001'],
			'data'    => ['name' => 'Jean'], 'locale' => 'fr', 'sync' => true,
		]);

		self::assertSame('Bonjour Jean', self::delivery($config, $whatsapp['records'][0]['id'])['text']);
		self::assertSame('fr', $whatsapp['records'][0]['locale']);
	}

	/**
	 * Not attempted, recorded, and not a failure of the caller.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testSkippedMessagesAreRecordedWithTheirReason(DbTestConfig $config): void
	{
		$hello = ['template' => 'test.hello', 'data' => ['name' => 'X']];

		$none = self::send($config, ['channel' => 'mail', 'to' => ['  ']] + $hello);
		$off  = self::send($config, ['channel' => 'off', 'to' => ['someone']] + $hello);

		\touch(self::project($config)->getPath() . '/unavailable.flag');

		try {
			$gone = self::send($config, ['channel' => 'whatsapp', 'to' => ['+22990000002']] + $hello);
		} finally {
			\unlink(self::project($config)->getPath() . '/unavailable.flag');
		}

		foreach ([[$none, 'no destination'], [$off, 'channel disabled'], [$gone, 'transport unavailable']] as [$a, $why]) {
			self::assertSame('skipped', $a['records'][0]['status'], \json_encode($a));
			self::assertSame($why, $a['records'][0]['last_error']);
			self::assertSame(0, $a['records'][0]['attempts']);
		}
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testAnUndeclaredChannelThrows(DbTestConfig $config): void
	{
		$answer = self::send($config, ['channel' => 'pigeon', 'template' => 'test.hello', 'to' => ['x']]);

		self::assertStringContainsString('The messaging channel "pigeon" is not declared', $answer['error'] ?? '');
	}

	/**
	 * @dataProvider provideDbConfig
	 */
	public function testAMessageSentOnceIsNotSentAgain(DbTestConfig $config): void
	{
		$spec = [
			'channel' => 'whatsapp', 'template' => 'test.hello', 'to' => ['+22990000003'],
			'data'    => ['name' => 'Once'], 'once' => 'registration:42', 'sync' => true,
		];

		self::assertCount(1, self::send($config, $spec)['records']);
		self::assertSame([], self::send($config, $spec)['records']);

		// About something else: another message.
		self::assertCount(1, self::send($config, ['once' => 'registration:43'] + $spec)['records']);

		$keyed = \array_filter(
			self::records($config),
			static fn (array $r) => 'registration:42' === $r['once_key']
		);

		self::assertCount(1, $keyed);

		$two = self::send($config, ['to' => ['+1', '+2'], 'once' => 'registration:44'] + $spec);

		self::assertStringContainsString('A message sent once has one recipient', $two['error'] ?? '');
	}

	/**
	 * A failed attempt after the response is retried by the `oz:messages` cron task: run by
	 * `oz cron run`, or by the tick a request runs once a minute when no scheduler does (the default
	 * `auto` runner). A task runs once per minute, so the retry may wait for the next one.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testAFailedAttemptIsRetriedByTheCronTask(DbTestConfig $config): void
	{
		$proj = self::project($config);

		\file_put_contents($proj->getPath() . '/fail.txt', '1');

		$answer = self::send($config, [
			'channel' => 'sms', 'template' => 'test.hello', 'to' => ['+22990000004'], 'data' => ['name' => 'R'],
		]);
		$id     = $answer['records'][0]['id'];

		// As a crontab does, every minute: a run in a minute whose task already ran does nothing.
		$deadline = \microtime(true) + 80;

		do {
			$proj->oz('cron', 'run')->mustRun();

			$record = self::record($config, $id);

			if ('queued' === $record['status']) {
				\sleep(5);
			}
		} while ('queued' === $record['status'] && \microtime(true) < $deadline);

		self::assertSame('sent', $record['status'], \json_encode($record));

		self::assertSame(2, $record['attempts']);
		self::assertNull($record['last_error']);
	}

	/**
	 * The last attempt fails the message, its error kept.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testTheLastFailedAttemptFailsTheMessage(DbTestConfig $config): void
	{
		$proj = self::project($config);

		\file_put_contents($proj->getPath() . '/fail.txt', 'always');

		try {
			$answer = self::send($config, [
				'channel' => 'sms', 'template' => 'test.hello', 'to' => ['+22990000005'], 'data' => ['name' => 'F'],
			]);
			$id     = $answer['records'][0]['id'];

			// The first attempt runs after the response; the second when the queue is run (here, or by a
			// tick a request runs).
			self::waitFor($config, $id, 'queued', 1, 5, true);

			$proj->oz('jobs', 'run', '--queue=oz:messages')->mustRun();

			$record = self::waitFor($config, $id, 'failed', null, 75);

			self::assertSame(2, $record['attempts']);
			self::assertSame('transport refused', $record['last_error']);

			// Synchronous: recorded as failed, then thrown at the caller.
			$sync = self::send($config, [
				'channel' => 'sms', 'template' => 'test.hello', 'to' => ['+22990000006'],
				'data'    => ['name' => 'S'], 'sync' => true,
			]);

			self::assertSame('transport refused', $sync['error'] ?? null);

			$last = \array_values(\array_filter(
				self::records($config),
				static fn (array $r) => '+22990000006' === $r['destination']
			));

			self::assertSame('failed', $last[0]['status']);
			self::assertSame(1, $last[0]['attempts']);
		} finally {
			\unlink($proj->getPath() . '/fail.txt');
		}
	}

	/**
	 * The default transport writes the message to the log, outside production.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testTheLogTransportWritesTheMessageToTheLog(DbTestConfig $config): void
	{
		$answer = self::send($config, [
			'channel' => 'logged', 'template' => 'test.hello', 'to' => ['log-me'], 'data' => ['name' => 'Logan'],
		]);

		self::assertSame('sent', $answer['records'][0]['status'], \json_encode($answer));

		$logs = '';

		foreach (\glob(self::project($config)->getPath() . '/.ozone/logs/*.log') ?: [] as $file) {
			$logs .= (string) \file_get_contents($file);
		}

		self::assertStringContainsString('[message] test.hello to "log-me" on logged', $logs);
		self::assertStringContainsString('Hello Logan, en', $logs);
	}

	/**
	 * OZone's own messages go the same way: the email of an ownership verification, and the SMS of a
	 * phone one, are recorded and sent after the response, with their subject and an unescaped link.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testOZoneVerificationMessagesAreSent(DbTestConfig $config): void
	{
		[$status, $body] = self::post($config, '/auth/verify/email', ['email' => 'ann@example.com']);

		self::assertSame(200, $status, $body);

		$mail = self::waitForTemplate($config, 'oz.auth.messages.verify.email', 'sent');
		$sent = self::delivery($config, $mail['id']);

		self::assertSame('ann@example.com', $sent['destination']);
		self::assertSame('Verify your email address', $sent['subject']);
		self::assertMatchesRegularExpression(
			'~^Verify your email address\n\nConfirm that this email address is yours\.\n\nhttp\S+/auth/link/\S+\n\n~',
			$sent['text']
		);
		self::assertStringContainsString('<html lang="en" dir="ltr">', (string) $sent['html']);
		self::assertStringNotContainsString('&amp;', $sent['text']);
		self::assertStringContainsString('<a href="', (string) $sent['html']);

		[$status, $body] = self::post($config, '/auth/verify/phone', ['phone' => '+22990000099']);

		self::assertSame(200, $status, $body);

		$sms = self::waitForTemplate($config, 'oz.auth.messages.verify.phone', 'sent');

		self::assertSame('+22990000099', self::delivery($config, $sms['id'])['destination']);
		self::assertMatchesRegularExpression(
			'~^.*: your phone verification code is \d+\. Do not share it\.$~',
			self::delivery($config, $sms['id'])['text']
		);
	}

	/**
	 * `oz doctor check` names the enabled channels that send nothing: a warning outside production.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testDoctorNamesTheChannelsThatSendNothing(DbTestConfig $config): void
	{
		$proc = self::project($config)->oz('doctor', 'check', '--json');
		$proc->run();

		$checks = [];

		foreach ((array) (\json_decode($proc->getOutput(), true)['data']['checks'] ?? []) as $check) {
			$checks[$check['name']] = $check;
		}

		self::assertSame('warn', $checks['Messaging: notification']['status'] ?? null, $proc->getOutput());
		self::assertSame('its transport writes to the log', $checks['Messaging: notification']['detail']);
		self::assertSame('warn', $checks['Messaging: logged']['status'] ?? null);
		self::assertArrayNotHasKey('Messaging: mail', $checks);
		self::assertArrayNotHasKey('Messaging: off', $checks);
	}

	/**
	 * The garbage collection deletes the settled records past their retention, keeps the ones sent once
	 * while their own retention is null, and deletes them once it is set. Last: it empties the table.
	 *
	 * @dataProvider provideDbConfig
	 */
	public function testOldRecordsAreDeletedByTheGarbageCollection(DbTestConfig $config): void
	{
		$proj = self::project($config);
		$once = static fn (array $r) => null !== $r['once_key'];

		self::assertNotEmpty(\array_filter(self::records($config), $once));

		// Kept with the default retention (90 days).
		self::get($config, '/messaging/gc');
		self::assertNotEmpty(\array_filter(self::records($config), static fn (array $r) => null === $r['once_key']));

		$proj->setSetting('oz.messaging', 'OZ_MESSAGING_RETENTION', 0);
		self::settingsSeen();
		self::get($config, '/messaging/gc');

		$left = self::records($config);

		self::assertNotEmpty($left);
		self::assertSame($left, \array_values(\array_filter($left, $once)), \json_encode($left));

		$proj->setSetting('oz.messaging', 'OZ_MESSAGING_ONCE_RETENTION', 0);
		self::settingsSeen();
		self::get($config, '/messaging/gc');

		self::assertSame([], self::records($config));
	}

	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('messaging');
	}

	/**
	 * Waits until the server sees a settings file just rewritten: opcache checks a file's timestamp
	 * every `opcache.revalidate_freq` seconds (2 in the image). Also puts the records settled before
	 * now a second behind.
	 */
	private static function settingsSeen(): void
	{
		\sleep(3);
	}

	private static function project(DbTestConfig $config): OZTestProject
	{
		if (!isset(self::$projects[$config->rdbms], self::$servers[$config->rdbms])) {
			self::fail(\sprintf('Project for %s not initialized. Did testSetup pass?', $config->rdbms));
		}

		return self::$projects[$config->rdbms];
	}

	/**
	 * @return array<string, mixed> the `data` of the answer
	 */
	private static function send(DbTestConfig $config, array $spec): array
	{
		return self::get($config, '/messaging/send?spec=' . \rawurlencode((string) \json_encode($spec)));
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function records(DbTestConfig $config): array
	{
		return self::get($config, '/messaging/records')['records'];
	}

	/**
	 * Polls a record until it has the status (and the attempts) asked for: work done after a response
	 * ends after the client has it.
	 *
	 * @return array<string, mixed>
	 */
	private static function waitFor(
		DbTestConfig $config,
		string $id,
		string $status,
		?int $attempts = null,
		int $timeout = 15,
		bool $or_settled = false
	): array {
		$deadline = \microtime(true) + $timeout;
		$record   = null;

		do {
			foreach (self::records($config) as $r) {
				if ($r['id'] === $id) {
					$record = $r;
				}
			}

			if (null !== $record && $status === $record['status'] && (null === $attempts || $attempts === $record['attempts'])) {
				return $record;
			}

			// Already past it: settled by a run the test did not ask for.
			if (null !== $record && $or_settled && 'queued' !== $record['status']) {
				return $record;
			}

			\usleep(200_000);
		} while (\microtime(true) < $deadline);

		self::fail(\sprintf('Message %s never reached "%s": %s', $id, $status, \json_encode($record)));
	}

	/**
	 * Polls for the record of a template until it has the status asked for.
	 *
	 * @return array<string, mixed>
	 */
	private static function waitForTemplate(DbTestConfig $config, string $template, string $status): array
	{
		$deadline = \microtime(true) + 15;
		$found    = null;

		do {
			foreach (self::records($config) as $r) {
				if ($template === $r['template']) {
					$found = $r;
				}
			}

			if (null !== $found && $status === $found['status']) {
				return $found;
			}

			\usleep(200_000);
		} while (\microtime(true) < $deadline);

		self::fail(\sprintf('No "%s" message reached "%s": %s', $template, $status, \json_encode($found)));
	}

	/**
	 * @param array<string, string> $fields
	 *
	 * @return array{0: int, 1: string}
	 */
	private static function post(DbTestConfig $config, string $path, array $fields): array
	{
		self::project($config);

		[, $host, $port] = self::$servers[$config->rdbms];

		$body   = @\file_get_contents("http://{$host}:{$port}{$path}", false, \stream_context_create(['http' => [
			'method'        => 'POST',
			'ignore_errors' => true,
			'timeout'       => 30,
			'header'        => "Accept: application/json\r\nContent-Type: application/x-www-form-urlencoded\r\n",
			'content'       => \http_build_query($fields),
		]]));
		$status = (int) \explode(' ', $http_response_header[0] ?? 'HTTP/1.1 0')[1];

		return [$status, (string) $body];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function record(DbTestConfig $config, string $id): array
	{
		foreach (self::records($config) as $r) {
			if ($r['id'] === $id) {
				return $r;
			}
		}

		self::fail(\sprintf('No message %s.', $id));
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function delivery(DbTestConfig $config, string $id): array
	{
		$file = self::project($config)->getPath() . '/deliveries.jsonl';

		foreach (\file($file, \FILE_IGNORE_NEW_LINES) ?: [] as $line) {
			$delivery = \json_decode($line, true);

			if ((string) $delivery['id'] === $id) {
				return $delivery;
			}
		}

		self::fail(\sprintf('Message %s was not delivered.', $id));
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function get(DbTestConfig $config, string $path): array
	{
		self::project($config);

		[, $host, $port] = self::$servers[$config->rdbms];

		$body = @\file_get_contents(
			"http://{$host}:{$port}{$path}",
			false,
			\stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 30]])
		);
		$json = \json_decode((string) $body, true);

		if (!\is_array($json) || !isset($json['data'])) {
			self::fail('Not an OZone answer: ' . $body);
		}

		return $json['data'];
	}
}
