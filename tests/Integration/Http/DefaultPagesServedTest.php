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

namespace OZONE\Tests\Integration\Http;

use OZONE\Core\Messaging\Transports\LogTransport;
use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * The default pages as a browser gets them from a real server: translated, themed by the project's
 * `oz.theme.css`, and the pages of an authorization link from the email to its confirmation.
 *
 * @internal
 */
#[CoversNothing]
final class DefaultPagesServedTest extends TestCase
{
	private static ?OZTestProject $project = null;

	/** @var null|array{0: Process, 1: string, 2: int} */
	private static ?array $server = null;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$proj = self::$project = OZTestProject::create('default-pages', fresh: true);

		$proj->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $proj->getPath() . '/default_pages.sqlite',
		]);
		$proj->setSetting('oz.config', 'OZ_PROJECT_NAME', 'Acme Events');
		// The verification email is written to the log, before the answer, for the test to read.
		$proj->setSetting('oz.messaging', 'OZ_MESSAGING_CHANNELS', [
			'mail' => ['transport' => LogTransport::class, 'async' => false],
		]);
		$proj->writeFile('app/templates/oz.theme.css', ':root { --oz-color-primary: #0b6e4f; }');
		// The project's sign-in page, which the pages that end an authorization link link to.
		$ns = $proj->getNamespace();
		$proj->writeFileFromStub('SignInPageRoutesProvider', 'app/SignInPageRoutesProvider.php', [
			'namespace' => $ns,
		]);
		$proj->setSetting('oz.routes.api', "{$ns}\\SignInPageRoutesProvider", true);
		$proj->setSetting('oz.auth', 'OZ_AUTH_SIGN_IN_ROUTE', 'app:sign-in');
		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();

		self::$server = $proj->startServer('api');
	}

	public static function tearDownAfterClass(): void
	{
		self::$server[0]->stop();
		self::$project?->destroy();
		self::$server  = null;
		self::$project = null;

		parent::tearDownAfterClass();
	}

	public function testAnErrorPageIsTranslatedAndThemedByTheProject(): void
	{
		[$status, $en] = self::request('GET', '/no-such-page');
		[, $fr]        = self::request('GET', '/no-such-page', [], ['Accept-Language: fr-FR,fr;q=0.9']);

		self::assertSame(404, $status);
		self::assertStringContainsString('<title>Page not found</title>', $en);
		self::assertStringContainsString('<div class="oz-card__mark" aria-hidden="true">A</div>', $en);
		self::assertStringContainsString('<footer class="oz-page__footer">Acme Events</footer>', $en);
		self::assertStringContainsString('<title>Page introuvable</title>', $fr);

		// OZone's variables, then the project's theme, which wins.
		$ours   = \strpos($en, '--oz-color-primary: #c4203d;');
		$theirs = \strpos($en, '--oz-color-primary: #0b6e4f;');

		self::assertNotFalse($ours);
		self::assertNotFalse($theirs);
		self::assertGreaterThan($ours, $theirs);
	}

	public function testAnUnknownLinkSaysItNoLongerWorks(): void
	{
		[$status, $page] = self::request('GET', '/auth/link/no-such-ref/no-such-token');

		self::assertSame(404, $status);
		self::assertStringContainsString('This link no longer works', $page);
	}

	public function testAnEmailLinkAsksForAClickThenConfirms(): void
	{
		[$status] = self::request('POST', '/auth/verify/email', ['email' => 'ann@example.com'], [
			'Accept: application/json',
		]);

		self::assertSame(200, $status);

		$link = self::linkFromTheLog();

		// Opening the link changes nothing: it asks for a click.
		[$status, $page, $headers] = self::request('GET', $link);

		self::assertSame(200, $status, $page);
		self::assertStringContainsString('Confirm it is you', $page);
		self::assertSame(1, \preg_match('~name="_csrf" value="([^"]*)"~', $page, $m));

		$cookies = self::cookies($headers);
		$post    = ['_csrf' => $m[1]];

		[$status, $page] = self::request('POST', $link, $post, $cookies);

		self::assertSame(200, $status, $page);
		self::assertStringContainsString('Confirmed', $page);
		self::assertStringContainsString('oz-badge--success', $page);

		// The way onward, built from the route OZ_AUTH_SIGN_IN_ROUTE names.
		[, $host] = self::$server;
		$link_to  = '~<a class="oz-btn" href="http://' . \preg_quote($host, '~') . '[^"]*/sign-in">Sign in</a>~';

		self::assertSame(1, \preg_match($link_to, $page), $page);
		self::assertStringContainsString('You can now sign in.', $page);
	}

	/**
	 * A sign-in route this scope cannot build is logged and leaves the page without a link, rather
	 * than failing a page that is reached once the authorization is already spent.
	 */
	public function testASignInRouteThatCannotBeBuiltIsLoggedAndLinksNothing(): void
	{
		// A real route, of every scope, but one that takes parameters.
		self::$project->setSetting('oz.auth', 'OZ_AUTH_SIGN_IN_ROUTE', 'oz:auth_link');
		self::serveAgain();

		try {
			[$status] = self::request('POST', '/auth/verify/email', ['email' => 'bob@example.com'], [
				'Accept: application/json',
			]);

			self::assertSame(200, $status);

			$link                      = self::linkFromTheLog();
			[$status, $page, $headers] = self::request('GET', $link);

			self::assertSame(200, $status, $page);
			self::assertSame(1, \preg_match('~name="_csrf" value="([^"]*)"~', $page, $m));

			[$status, $page] = self::request('POST', $link, ['_csrf' => $m[1]], self::cookies($headers));

			self::assertSame(200, $status, $page);
			self::assertStringContainsString('Confirmed', $page);
			self::assertStringNotContainsString('<div class="oz-card__actions">', $page);
			self::assertStringContainsString('You can close this window', $page);
			self::assertStringContainsString('OZ_AUTH_SIGN_IN_ROUTE names no parameterless route', self::logs());
		} finally {
			self::$project->setSetting('oz.auth', 'OZ_AUTH_SIGN_IN_ROUTE', 'app:sign-in');
		}
	}

	/**
	 * Starts the server again, for a settings change to reach it: the dev server is one long-lived
	 * process, and OPcache holds the settings file it has already compiled (`opcache.revalidate_freq`),
	 * so a file rewritten in place between two requests is not what the next one reads.
	 */
	private static function serveAgain(): void
	{
		self::$server[0]->stop();
		self::$server = self::$project->startServer('api');
	}

	/**
	 * The path of the last authorization link the log transport wrote.
	 */
	private static function linkFromTheLog(): string
	{
		$logs = self::logs();

		self::assertGreaterThan(0, \preg_match_all('~https?://[^\s"<]+(/auth/link/[^\s"<]+)~', $logs, $m), $logs);

		return (string) \array_last($m[1]);
	}

	/**
	 * Everything the project has logged so far.
	 */
	private static function logs(): string
	{
		$logs = '';

		foreach (\glob(self::$project->getPath() . '/.ozone/logs/*.log') ?: [] as $file) {
			$logs .= (string) \file_get_contents($file);
		}

		return $logs;
	}

	/**
	 * @param array<string, string> $fields
	 * @param list<string>          $headers
	 *
	 * @return array{0: int, 1: string, 2: list<string>}
	 */
	private static function request(string $method, string $path, array $fields = [], array $headers = []): array
	{
		[, $host, $port] = self::$server;

		$opts = [
			'method'          => $method,
			'ignore_errors'   => true,
			'follow_location' => false,
			'timeout'         => 30,
			'header'          => \implode('', \array_map(static fn (string $h) => $h . "\r\n", $headers)),
		];

		if ([] !== $fields) {
			$opts['content'] = \http_build_query($fields);
			$opts['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
		}

		$body   = @\file_get_contents("http://{$host}:{$port}{$path}", false, \stream_context_create(['http' => $opts]));
		$status = (int) \explode(' ', (\http_get_last_response_headers() ?? [])[0] ?? 'HTTP/1.1 0')[1];

		return [$status, (string) $body, \http_get_last_response_headers() ?? []];
	}

	/**
	 * The `Cookie` header that sends back what a response set.
	 *
	 * @param list<string> $headers
	 *
	 * @return list<string>
	 */
	private static function cookies(array $headers): array
	{
		$pairs = [];

		foreach ($headers as $header) {
			if (\preg_match('~^Set-Cookie:\s*([^;]+)~i', $header, $m)) {
				$pairs[] = $m[1];
			}
		}

		return [] === $pairs ? [] : ['Cookie: ' . \implode('; ', $pairs)];
	}
}
