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

namespace OZONE\Tests\Maintenance;

use Override;
use OZONE\Core\Access\AccessRights;
use OZONE\Core\Access\Interfaces\AccessRightsInterface;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodInterface;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Cli\Cron\CronEndpoint;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Exceptions\UnauthenticatedException;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Maintenance\Enums\MaintenanceCoverage;
use OZONE\Core\Maintenance\MaintenanceException;
use OZONE\Core\Maintenance\MaintenanceGate;
use OZONE\Core\Maintenance\MaintenanceMode;
use OZONE\Core\Maintenance\Views\MaintenanceView;
use OZONE\Core\Roles\Enums\Role;
use OZONE\Core\Roles\Roles;
use OZONE\Core\Roles\RolesUtils;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Tests\Support\TestAuthUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class MaintenanceGateTest.
 *
 * Who a maintenance lets through, and what it answers everybody else.
 *
 * @internal
 */
#[CoversClass(MaintenanceGate::class)]
#[CoversClass(MaintenanceException::class)]
#[CoversClass(MaintenanceView::class)]
final class MaintenanceGateTest extends TestCase
{
	// No dot in the key: dotted settings keys are read as paths.
	private const string PROXY_RANGE = '2001:db8::/32';

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		db()->executeMulti(db()->getGenerator()->buildDatabase());
	}

	#[Override]
	protected function tearDown(): void
	{
		Settings::unset('oz.proxies', self::PROXY_RANGE);
		MaintenanceGateTestAuth::$user   = null;
		MaintenanceGateTestAuth::$scoped = false;
	}

	public function testTheScopeServingTheRequestDecidesWhetherItIsHeld(): void
	{
		// The sandbox serves its requests as the application itself: scope `root`.
		[$all]    = MaintenanceMode::start();
		[$named]  = MaintenanceMode::start(MaintenanceCoverage::SCOPES, ['root']);
		[$others] = MaintenanceMode::start(MaintenanceCoverage::SCOPES, ['api', 'www']);
		[$api]    = MaintenanceMode::start(MaintenanceCoverage::API);
		[$web]    = MaintenanceMode::start(MaintenanceCoverage::WEB);

		self::assertTrue(new MaintenanceGate($all)->covers(self::context()));
		self::assertTrue(new MaintenanceGate($named)->covers(self::context()));
		self::assertFalse(new MaintenanceGate($others)->covers(self::context()));
		self::assertTrue(new MaintenanceGate($api)->covers(self::context()));
		self::assertFalse(new MaintenanceGate($api)->covers(self::context(api: false)));
		self::assertTrue(new MaintenanceGate($web)->covers(self::context(api: false)));
		self::assertFalse(new MaintenanceGate($web)->covers(self::context()));
	}

	public function testAnAllowedAddressAndAnAddressOfAnAllowedRangeAreLetThrough(): void
	{
		[$mode] = MaintenanceMode::start(allow: ['203.0.113.7', '10.0.0.0/8']);
		$gate   = new MaintenanceGate($mode);

		self::assertTrue($gate->letsThrough(self::context(['REMOTE_ADDR' => '203.0.113.7'])));
		self::assertTrue($gate->letsThrough(self::context(['REMOTE_ADDR' => '10.42.0.9'])));
		self::assertFalse($gate->letsThrough(self::context(['REMOTE_ADDR' => '203.0.113.8'])));
		self::assertFalse($gate->letsThrough(self::context(['REMOTE_ADDR' => '11.0.0.1'])));
	}

	public function testTheAddressIsTheOneTheTrustedProxiesReport(): void
	{
		[$mode] = MaintenanceMode::start(allow: ['198.51.100.9']);
		$gate   = new MaintenanceGate($mode);

		$behind_proxy = ['REMOTE_ADDR' => '2001:db8::1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.9'];

		// Not trusted yet: the header is the client's own claim, and the peer is the proxy.
		self::assertFalse($gate->letsThrough(self::context($behind_proxy)));

		Settings::set('oz.proxies', self::PROXY_RANGE, true);

		self::assertTrue($gate->letsThrough(self::context($behind_proxy)));
		// A client that is no proxy names the allowed address in the header: it is not believed.
		self::assertFalse($gate->letsThrough(self::context([
			'REMOTE_ADDR'          => '192.0.2.50',
			'HTTP_X_FORWARDED_FOR' => '198.51.100.9',
		])));
	}

	public function testTheSecretLinkSetsTheCookieAndSendsTheBrowserBackWithoutTheSecret(): void
	{
		[$mode, $secret] = MaintenanceMode::start();
		$gate            = new MaintenanceGate($mode);
		$query           = 'a=1&' . MaintenanceGate::LINK_PARAM . '=' . $secret . '&b=2';

		$response = $gate->followLink(self::context([
			'REQUEST_URI'  => '/shop/cart?' . $query,
			'QUERY_STRING' => $query,
		]));

		self::assertNotNull($response);
		self::assertSame(302, $response->getStatusCode());
		self::assertSame('/shop/cart?a=1&b=2', $response->getHeaderLine('Location'));
		self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));

		$cookie = $response->getHeaderLine('Set-Cookie');

		self::assertStringStartsWith(MaintenanceGate::COOKIE . '=' . (string) $mode->bypassToken() . ';', $cookie);
		self::assertStringContainsStringIgnoringCase('HttpOnly', $cookie);
		self::assertStringNotContainsString($secret, $cookie);

		// The browser comes back with the cookie: let through.
		self::assertTrue($gate->letsThrough(self::withCookie((string) $mode->bypassToken())));
	}

	public function testTheLinkSendsTheBrowserNowhereButTheSamePath(): void
	{
		[$mode, $secret] = MaintenanceMode::start();
		$query           = MaintenanceGate::LINK_PARAM . '=' . $secret;

		$location = new MaintenanceGate($mode)->followLink(self::context([
			'REQUEST_URI'  => '//evil.example/x?' . $query,
			'QUERY_STRING' => $query,
		]))?->getHeaderLine('Location');

		self::assertSame('/evil.example/x', $location);
	}

	public function testAWrongSecretOrNoneIsNoLink(): void
	{
		[$mode, $secret] = MaintenanceMode::start();
		$gate            = new MaintenanceGate($mode);

		foreach (['nope', \hash('sha256', $secret), ''] as $given) {
			$query = MaintenanceGate::LINK_PARAM . '=' . $given;

			$context = self::context(['REQUEST_URI' => '/?' . $query, 'QUERY_STRING' => $query]);

			self::assertNull($gate->followLink($context));
		}

		self::assertNull($gate->followLink(self::context()));
	}

	public function testTheCookieOfAnotherRunOrMadeUpIsRefused(): void
	{
		[$mode]   = MaintenanceMode::start();
		[$next]   = MaintenanceMode::start();
		$cookie   = (string) $mode->bypassToken();
		$next_run = new MaintenanceGate($next);

		// `on` again: the cookie of the maintenance before no longer works.
		self::assertFalse($next_run->letsThrough(self::withCookie($cookie)));
		// What the record holds is not enough to make one: the cookie is signed with the project secret.
		$unsigned = \hash_hmac('sha256', 'oz:maintenance:' . $next->run, '');

		self::assertFalse($next_run->letsThrough(self::withCookie($unsigned)));
		self::assertFalse($next_run->letsThrough(self::withCookie($next->run)));
		self::assertFalse($next_run->letsThrough(self::withCookie('')));
		self::assertTrue($next_run->letsThrough(self::withCookie((string) $next->bypassToken())));
	}

	public function testASignedInAdministratorIsLetThrough(): void
	{
		[$mode] = MaintenanceMode::start();
		$gate   = new MaintenanceGate($mode);

		self::assertTrue($gate->admits(self::authenticatedAs(self::userWith('8101', Role::ADMIN))));
		// a role weighing more than admin
		self::assertTrue($gate->admits(self::authenticatedAs(self::userWith('8102', Role::SUPER_ADMIN))));
	}

	public function testAUserWithoutTheRoleIsHeld(): void
	{
		[$mode] = MaintenanceMode::start();
		$gate   = new MaintenanceGate($mode);

		self::assertFalse($gate->admits(self::authenticatedAs(self::userWith('8103', Role::EDITOR))));
		self::assertFalse($gate->admits(self::authenticatedAs(self::userWith('8104', null))));
	}

	public function testNoAuthenticationIsHeld(): void
	{
		[$mode] = MaintenanceMode::start();

		self::assertFalse(new MaintenanceGate($mode)->admits(self::context()));
	}

	public function testAnAuthenticationThatCannotSayWhoIsAskingIsHeld(): void
	{
		[$mode] = MaintenanceMode::start();

		// user() throws, as a session that does not load does
		self::assertFalse(new MaintenanceGate($mode)->admits(self::authenticatedAs(null)));
	}

	public function testAScopedAuthenticationIsHeldWhateverTheRolesOfItsUser(): void
	{
		[$mode] = MaintenanceMode::start();
		$admin  = self::userWith('8105', Role::ADMIN);

		MaintenanceGateTestAuth::$scoped = true;

		self::assertFalse(new MaintenanceGate($mode)->admits(self::authenticatedAs($admin)));
	}

	public function testAnAdministratorWhoseRolesCannotBeReadIsHeld(): void
	{
		[$mode] = MaintenanceMode::start();
		$admin  = self::userWith('8106', Role::ADMIN);
		$table  = db()->getTableOrFail('oz_roles')->getFullName();

		// The roles table being rewritten by a migration: the query fails.
		db()->executeMulti('DROP TABLE ' . $table . ';');

		try {
			// What the request read of the roles so far is forgotten, as a new request starts with
			// nothing read; reading them again is what fails.
			RolesUtils::roles($admin, true);
			self::fail('The roles were read with their table gone.');
		} catch (RuntimeException) {
		}

		try {
			self::assertFalse(new MaintenanceGate($mode)->admits(self::authenticatedAs($admin)));
		} finally {
			db()->executeMulti(db()->getGenerator()->buildDatabase());
		}
	}

	public function testARequestRefusedOnceAuthenticatedKeepsNothingOfItsAuthentication(): void
	{
		[$mode] = MaintenanceMode::start();
		$gate   = new MaintenanceGate($mode);

		$admin = self::authenticatedAs(self::userWith('8107', Role::ADMIN));

		$gate->screenAuthenticated($admin);

		self::assertNotNull($admin->authState()->current());

		$editor = self::authenticatedAs(self::userWith('8108', Role::EDITOR));

		try {
			$gate->screenAuthenticated($editor);
			self::fail('A user who is no administrator was let through.');
		} catch (MaintenanceException $e) {
			self::assertSame($mode, $e->getMode());
		}

		// Nothing left for the answer to persist: a session is not written while a migration runs.
		self::assertNull($editor->authState()->current());
	}

	public function testOnlyTheCronRouteIsServedWhateverTheMaintenance(): void
	{
		[$mode] = MaintenanceMode::start();
		$gate   = new MaintenanceGate($mode);

		$router = new Router();
		$router->get(CronEndpoint::PATH, static fn () => null)->name(CronEndpoint::ROUTE);
		$router->get('/other', static fn () => null)->name('test:maintenance.other');

		$cron  = new RouteInfo(self::context(), $router->requireRoute(CronEndpoint::ROUTE), []);
		$other = new RouteInfo(self::context(), $router->requireRoute('test:maintenance.other'), []);

		self::assertTrue($gate->exempts($cron));
		self::assertFalse($gate->exempts($other));
	}

	public function testAnApiRequestIsAnswered503InTheEnvelopeOfEveryAnswer(): void
	{
		[$mode] = MaintenanceMode::start(retry_after: 120, message: 'Back at noon');

		// What the client accepts does not matter to an API scope.
		$response = MaintenanceException::of($mode)->answer(self::context(['HTTP_ACCEPT' => 'text/html']));
		$body     = \json_decode((string) $response->getBody(), true);

		self::assertSame(503, $response->getStatusCode());
		self::assertSame('120', $response->getHeaderLine('Retry-After'));
		self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
		self::assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
		self::assertIsArray($body);
		self::assertSame(1, $body['error'] ?? null);
		self::assertSame('OZ_MAINTENANCE', $body['msg'] ?? null);
		self::assertSame(['retry_after' => 120, 'message' => 'Back at noon'], $body['data'] ?? null);
		self::assertIsInt($body['utime'] ?? null);
	}

	public function testAWebPageIsAnswered503WithTheMaintenancePage(): void
	{
		[$mode] = MaintenanceMode::start(retry_after: 600, message: 'Back <b>soon</b>');

		$response = MaintenanceException::of($mode)->answer(self::context(api: false));
		$body     = (string) $response->getBody();

		self::assertSame(503, $response->getStatusCode());
		self::assertSame('600', $response->getHeaderLine('Retry-After'));
		self::assertStringStartsWith('text/html', $response->getHeaderLine('Content-Type'));
		self::assertStringContainsString('<title>Under maintenance</title>', $body);
		self::assertStringContainsString('The service is under maintenance. Please try again later.', $body);
		// the operator's text, as text
		self::assertStringContainsString('Back &lt;b&gt;soon&lt;/b&gt;', $body);
		self::assertStringContainsString('class="oz-page"', $body);
	}

	public function testAWebRequestAskingForJsonOnlyGetsTheEnvelope(): void
	{
		[$mode] = MaintenanceMode::start();

		$context  = self::context(['HTTP_ACCEPT' => 'application/json'], false);
		$response = MaintenanceException::of($mode)->answer($context);

		self::assertSame(503, $response->getStatusCode());
		self::assertSame('OZ_MAINTENANCE', \json_decode((string) $response->getBody(), true)['msg'] ?? null);
	}

	public function testNoRetryAfterWhenTheMaintenanceGaveNone(): void
	{
		[$mode] = MaintenanceMode::start();

		$response = MaintenanceException::of($mode)->answer(self::context());

		self::assertFalse($response->hasHeader('Retry-After'));
		self::assertNull(\json_decode((string) $response->getBody(), true)['data']['retry_after'] ?? null);
	}

	public function testTheRefusalIsWhatTheContextAnswers(): void
	{
		[$mode] = MaintenanceMode::start(retry_after: 30);
		$context = self::context();

		new MaintenanceGate($mode)->refusal()->informClient($context);

		self::assertSame(503, $context->getResponse()->getStatusCode());
		self::assertSame('30', $context->getResponse()->getHeaderLine('Retry-After'));
	}

	/**
	 * A request of the sandbox, as a sub-request of its root context.
	 */
	private static function context(array $env = [], bool $api = true): Context
	{
		return new Context(HTTPEnvironment::mock($env), null, Context::root(), $api);
	}

	private static function withCookie(string $value): Context
	{
		return self::context(['HTTP_COOKIE' => MaintenanceGate::COOKIE . '=' . $value]);
	}

	/**
	 * A request authenticated, by its route's method, as a user; or by a method that cannot say who
	 * is asking, for null.
	 */
	private static function authenticatedAs(?AuthUserInterface $user): Context
	{
		MaintenanceGateTestAuth::$user = $user;

		$router = new Router();
		$router->get('/held', static fn () => null)
			->name('test:maintenance.held')
			->withAuthentication(MaintenanceGateTestAuth::class);

		$context = self::context();

		$authenticate = static function (RouteInfo $ri) use ($context): void {
			$context->authState()->authenticate($ri);
		};

		new RouteInfo($context, $router->requireRoute('test:maintenance.held'), [], $authenticate);

		return $context;
	}

	private static function userWith(string $id, ?Role $role): TestAuthUser
	{
		$user = new TestAuthUser('user', $id);

		if (null !== $role) {
			Roles::assign($user, $role);
		}

		RolesUtils::roles($user, true);

		return $user;
	}
}

/**
 * The authentication method of a route under test: it says who is asking, or that it cannot, and
 * whether it is scoped, as a test sets it.
 *
 * @internal
 */
final class MaintenanceGateTestAuth implements AuthenticationMethodInterface
{
	public static ?AuthUserInterface $user = null;

	public static bool $scoped = false;

	#[Override]
	public static function get(RouteInfo $ri, string $realm): static
	{
		return new self();
	}

	#[Override]
	public function satisfied(): bool
	{
		return true;
	}

	#[Override]
	public function authenticate(): void {}

	#[Override]
	public function user(): AuthUserInterface
	{
		return self::$user ?? throw new UnauthenticatedException();
	}

	#[Override]
	public function getAccessRights(): AccessRightsInterface
	{
		return new AccessRights();
	}

	#[Override]
	public function isScoped(): bool
	{
		return self::$scoped;
	}

	#[Override]
	public function ask(): void {}
}
