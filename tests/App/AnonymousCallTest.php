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

namespace OZONE\Tests\App;

use Gobl\ORM\ORMOptions;
use LogicException;
use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\Navigator;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Enums\AuthenticationMethodScheme;
use OZONE\Core\Cli\Deploy\DeployInitializer;
use OZONE\Core\Db\OZSessionsQuery;
use OZONE\Core\Exceptions\BaseException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\Assets;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\OZone;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Runtime\Runtime;
use OZONE\Core\Runtime\WorkerRuntime;
use OZONE\Core\Scopes\Interfaces\ScopeInterface;
use OZONE\Core\Scopes\ScopeKind;
use OZONE\Tests\Support\AnonymousCallRoutes;
use OZONE\Tests\Support\HeaderTestAuth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Class AnonymousCallTest.
 *
 * A route of another scope of the project, called in process as nobody: the scope's settings,
 * templates and routers in use for the call and the caller's back after it, whatever happened; what
 * comes back is what a visitor would get; and nothing of the caller -- identity, session, buffers,
 * errors -- reaches the call or is left changed by it.
 *
 * The scopes are made in the suite's sandbox, under the console runtime, which is what a job runs
 * under: the first consumer of the call.
 *
 * @internal
 */
#[CoversClass(Navigator::class)]
#[CoversClass(OZone::class)]
#[CoversClass(Settings::class)]
#[CoversClass(Assets::class)]
final class AnonymousCallTest extends TestCase
{
	private const string SCOPE = 'anon-call';

	private const string PLUGGED = 'anon-plugged';

	private const string REENTRANT = 'anon-reentrant';

	private const string LOGGED = 'anon-logged';

	/**
	 * A route provider of the called scope, living in that scope's own directory.
	 */
	private const string SCOPED_PROVIDER = 'AnonCallScopedRoutes';

	private const string ORIGIN = 'http://anon.example.test:8086';

	private const string TEMPLATE = 'anon.probe.txt';

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$scoped = self::scopedProvider();

		self::makeScope(self::SCOPE, [
			'oz.routes.api'                  => [AnonymousCallRoutes::class => true, $scoped => true],
			'oz.request'                     => ['OZ_DEFAULT_ORIGIN' => self::ORIGIN],
			// The header first: a request carrying it is signed in, before the session, which is
			// always satisfied, can answer for it.
			'oz.auth'                        => [
				'OZ_AUTH_API_AUTH_METHODS' => [HeaderTestAuth::class, AuthenticationMethodScheme::SESSION],
			],
			AnonymousCallRoutes::PROBE_GROUP => ['WHERE' => 'scope'],
		]);

		\file_put_contents(self::scopeDir(self::SCOPE) . '/templates/' . self::TEMPLATE, 'scope');

		// Found through the project namespace, which reads the scope's own directory once the scope
		// is entered, as its entry point makes it read it.
		\file_put_contents(
			self::scopeDir(self::SCOPE) . '/' . self::SCOPED_PROVIDER . '.php',
			\sprintf(
				"<?php\n\ndeclare(strict_types=1);\n\nnamespace %s;\n\n"
					. "use OZONE\\Core\\Http\\Response;\n"
					. "use OZONE\\Core\\Router\\Interfaces\\RouteProviderInterface;\n"
					. "use OZONE\\Core\\Router\\RouteInfo;\n"
					. "use OZONE\\Core\\Router\\Router;\n\n"
					. "final class %s implements RouteProviderInterface\n{\n"
					. "\tpublic static function routesVersion(): ?string\n\t{\n\t\treturn null;\n\t}\n\n"
					. "\tpublic static function registerRoutes(Router \$router): void\n\t{\n"
					. "\t\t\$router->get('/anon/scoped', static fn (RouteInfo \$ri): Response => \$ri->getContext()"
					. "->getResponse()->withHeader('X-Scoped', 'yes'))->name('anon:scoped');\n"
					. "\t}\n}\n",
				\substr($scoped, 0, (int) \strrpos($scoped, '\\')),
				self::SCOPED_PROVIDER
			)
		);

		// A plugin list of its own: what a process wired at boot and cannot wire again.
		self::makeScope(self::PLUGGED, [
			'oz.plugins' => ['OZONE\Tests\App\NoSuchPlugin' => false],
		]);

		// Logs of its own: what a process wired for its own scope, and a deploy still has to name.
		self::makeScope(self::LOGGED, [
			'oz.logs'    => ['OZ_LOG_LEVEL_OF_ANON_LOGGED' => 'debug'],
			'oz.request' => ['OZ_DEFAULT_ORIGIN' => 'https://logged.example.test'],
		]);

		// A settings file of a group compared when the scope is first entered, which reaches for its
		// own scope again through another one, while that scope's settings are being put together.
		self::makeScope(self::REENTRANT, []);

		\file_put_contents(
			self::scopeDir(self::REENTRANT) . '/settings/oz.logs.php',
			"<?php\n\ndeclare(strict_types=1);\n\nuse OZONE\\Core\\OZone;\n\n"
				. "return OZone::inScope(app()->getScope('root'), static fn (): array => OZone::inScope(\n"
				. "\tapp()->getScope('" . self::REENTRANT . "'),\n"
				. "\tstatic fn (): array => []\n"
				. "));\n"
		);

		self::writeSettings(
			app()->getSettingsDir()->getRoot(),
			[AnonymousCallRoutes::PROBE_GROUP => ['WHERE' => 'app']]
		);
	}

	/**
	 * The scopes go with the class: the sandbox is shared by the suite, and what lists a project's
	 * scopes (a deploy plan, a build) would find them, the one this process cannot serve among them.
	 * What this process kept of them stays, under names nothing else uses.
	 */
	#[Override]
	public static function tearDownAfterClass(): void
	{
		$project = app()->getProjectDir()->getRoot();

		foreach ([self::SCOPE, self::PLUGGED, self::REENTRANT, self::LOGGED] as $name) {
			self::remove(self::scopeDir($name));
			self::remove($project . '/data/' . $name);
		}

		@\unlink(app()->getSettingsDir()->getRoot() . '/' . AnonymousCallRoutes::PROBE_GROUP . '.php');

		parent::tearDownAfterClass();
	}

	public function testTheScopeIsInUseInsideAndTheCallersAfterwards(): void
	{
		$scope = app()->getScope(self::SCOPE);
		$seen  = OZone::inScope($scope, static fn(): array => [
			scope()->getName(),
			Settings::get(AnonymousCallRoutes::PROBE_GROUP, 'WHERE'),
		]);

		self::assertSame([self::SCOPE, 'scope'], $seen);
		self::assertCallersScope();

		try {
			OZone::inScope($scope, static function (): never {
				throw new LogicException('thrown inside');
			});

			self::fail('The throw did not come out of the switch.');
		} catch (LogicException $e) {
			self::assertSame('thrown inside', $e->getMessage());
		}

		self::assertCallersScope();
	}

	public function testTheScopesRoutersAndTemplatesAreItsOwn(): void
	{
		$outer_router = OZone::getApiRouter();

		// Asked outside first: what the lookup keeps for this request must not answer for the scope.
		self::assertFalse(Assets::localize('oz://' . self::TEMPLATE));

		[$router, $template] = OZone::inScope(
			app()->getScope(self::SCOPE),
			static fn(): array => [OZone::getApiRouter(), Assets::localize('oz://' . self::TEMPLATE)]
		);

		self::assertNotSame($outer_router, $router);
		self::assertNotNull($router->getRoute('anon:where'));
		self::assertNull($outer_router->getRoute('anon:where'));
		self::assertIsString($template);
		self::assertSame('scope', \file_get_contents($template));

		self::assertSame($outer_router, OZone::getApiRouter());
		self::assertFalse(Assets::localize('oz://' . self::TEMPLATE));
	}

	public function testACallIsAnsweredAsAVisitorWouldBe(): void
	{
		$root    = Context::root();
		$current = Context::current();

		$response = $root->callAnonymously('anon:where', [], [], self::SCOPE);

		self::assertSame(200, $response->getStatusCode());
		self::assertSame(self::SCOPE, $response->getHeaderLine('X-Scope'));
		self::assertSame('scope', $response->getHeaderLine('X-Where'));
		self::assertSame('no', $response->getHeaderLine('X-User'));
		self::assertSame('no', $response->getHeaderLine('X-Sub'));
		self::assertSame('127.0.0.1', $response->getHeaderLine('X-IP'));
		self::assertSame('anon.example.test:8086', $response->getHeaderLine('X-Host'));
		self::assertSame('', $response->getHeaderLine('X-Accept-Language'));
		// `ResponseHook` ran for it, as for any visitor.
		self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));

		self::assertCallersScope();
		self::assertSame($root, Context::root());
		self::assertSame($current, Context::current());
	}

	public function testALanguageIsAskedForOnlyWhenGiven(): void
	{
		$response = Context::root()->callAnonymously('anon:where', [], [], self::SCOPE, 'fr');

		self::assertSame('fr', $response->getHeaderLine('X-Accept-Language'));
	}

	public function testTheScopeInUseIsCalledWithoutASwitch(): void
	{
		$response = Context::root()->callAnonymously('oz:robots');

		self::assertSame(200, $response->getStatusCode());
		self::assertStringContainsString('Disallow: /', (string) $response->getBody());
	}

	/**
	 * The caller is signed in; the call is nobody, so a route for members refuses it as it refuses a
	 * visitor with no credentials (401, the guard's answer to nobody). A sub-request reuses its
	 * parent's authentication, and this is exactly what the call must not do.
	 */
	public function testASignedInCallerIsNobodyInsideTheCall(): void
	{
		$caller = self::signedInCaller('7');

		self::assertTrue($caller->hasAuthenticatedUser());

		self::assertSame(401, $caller->callAnonymously('anon:members', [], [], self::SCOPE)->getStatusCode());
		self::assertSame('no', $caller->callAnonymously('anon:where', [], [], self::SCOPE)->getHeaderLine('X-User'));

		self::assertTrue($caller->hasAuthenticatedUser());
	}

	public function testACallKeepsNoSession(): void
	{
		$before   = self::sessionCount();
		$response = Context::root()->callAnonymously('anon:session', [], [], self::SCOPE);

		self::assertSame(200, $response->getStatusCode());
		self::assertFalse($response->hasHeader('Set-Cookie'));
		self::assertSame($before, self::sessionCount());
	}

	public function testARedirectionIsAnsweredAsOne(): void
	{
		$response = Context::root()->callAnonymously('anon:redirect', [], [], self::SCOPE);

		self::assertSame(302, $response->getStatusCode());
		self::assertSame(self::ORIGIN . '/anon/where', $response->getHeaderLine('Location'));
	}

	/**
	 * Failing calls are answered with their status, and the caller goes on: a later call is served,
	 * and a call whose own sub-requests fail twice is answered too.
	 */
	public function testFailingCallsLeaveTheCallerGoingOn(): void
	{
		$root = Context::root();

		self::assertSame(500, $root->callAnonymously('anon:fail', [], [], self::SCOPE)->getStatusCode());
		self::assertSame(500, $root->callAnonymously('anon:fail', [], [], self::SCOPE)->getStatusCode());
		self::assertSame(200, $root->callAnonymously('anon:where', [], [], self::SCOPE)->getStatusCode());

		// Under a worker, where a report that fails unwinds rather than ending this process.
		$runtime = Runtime::current();

		Runtime::set(new WorkerRuntime('anonymous-call'));

		try {
			$response = $root->callAnonymously('anon:fail-twice', [], [], self::SCOPE);
		} finally {
			Runtime::set($runtime);
		}

		self::assertSame(200, $response->getStatusCode());
		self::assertSame('500,500', $response->getHeaderLine('X-Statuses'));
	}

	/**
	 * A call made while an error is being reported -- an error page that calls a route -- reports its
	 * own errors as any request does, rather than taking the first for a failure to report.
	 */
	public function testACallMadeWhileAnErrorIsReportedReportsItsOwn(): void
	{
		$reporting = new ReflectionProperty(BaseException::class, 'just_die');
		$runtime   = Runtime::current();

		// Under a worker, where the last resort of a failed report unwinds rather than ending this
		// process.
		Runtime::set(new WorkerRuntime('anonymous-call'));
		$reporting->setValue(null, true);

		try {
			$response = Context::root()->callAnonymously('anon:fail', [], [], self::SCOPE);

			self::assertTrue($reporting->getValue(), 'The report under way is no longer under way.');
		} finally {
			$reporting->setValue(null, false);
			Runtime::set($runtime);
		}

		self::assertSame(500, $response->getStatusCode());
		// The API's answer to an error, not the page of a report that failed.
		self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
	}

	public function testTheCallersBufferSurvivesAFailingCall(): void
	{
		$level = \ob_get_level();

		\ob_start();

		echo 'outer';

		$response = Context::root()->callAnonymously('anon:fail', [], [], self::SCOPE);
		$output   = \ob_get_clean();

		self::assertSame(500, $response->getStatusCode());
		self::assertSame('outer', $output);
		self::assertSame($level, \ob_get_level());
	}

	public function testCallsNestedPastTheBoundAreRefused(): void
	{
		self::assertSame(16, Settings::get('oz.request', 'OZ_ANONYMOUS_CALL_MAX_DEPTH'));

		// From here: the call for 15 is the first of sixteen, the last one reaching the bottom.
		$reached = Context::root()->callAnonymously('anon:depth', ['n' => 15], [], self::SCOPE);

		self::assertSame('yes', $reached->getHeaderLine('X-Reached'));

		// Seventeen.
		$refused = Context::root()->callAnonymously('anon:depth', ['n' => 16], [], self::SCOPE);

		self::assertSame('', $refused->getHeaderLine('X-Reached'));
		self::assertStringContainsString('OZ_ANONYMOUS_CALL_MAX_DEPTH (16)', $refused->getHeaderLine('X-Refused'));
		self::assertStringContainsString(
			'anon:depth in anon-call {"params":{"n":16}} -> anon:depth in anon-call {"params":{"n":15}}',
			$refused->getHeaderLine('X-Refused')
		);
	}

	public function testTheBoundIsTheProjectsToRaise(): void
	{
		Settings::set('oz.request', 'OZ_ANONYMOUS_CALL_MAX_DEPTH', 32);

		try {
			$reached = Context::root()->callAnonymously('anon:depth', ['n' => 31], [], self::SCOPE);

			self::assertSame('yes', $reached->getHeaderLine('X-Reached'), $reached->getHeaderLine('X-Refused'));
		} finally {
			Settings::unset('oz.request', 'OZ_ANONYMOUS_CALL_MAX_DEPTH');
		}
	}

	public function testTheSameCallInsideItselfIsRefusedNamingTheChain(): void
	{
		$response = Context::root()->callAnonymously('anon:cycle', [], [], self::SCOPE);

		self::assertSame(
			'A route called as nobody calls itself with the same inputs, which never ends:'
				. ' anon:cycle in anon-call -> anon:cycle in anon-call.',
			$response->getHeaderLine('X-Refused')
		);
	}

	/**
	 * A call into the scope it was made from, from inside a call: the caller's scope comes back in
	 * use for it, and the call's own scope after it.
	 */
	public function testACallBackIntoTheCallersScopeReturnsToTheCall(): void
	{
		$response = Context::root()->callAnonymously('anon:back', [], [], self::SCOPE);

		self::assertSame('200', $response->getHeaderLine('X-Back'));
		self::assertSame(self::SCOPE, $response->getHeaderLine('X-Scope'));
		self::assertSame('scope', $response->getHeaderLine('X-Where'));

		self::assertCallersScope();
	}

	public function testAScopeDeclaringAProcessWideGroupOtherwiseIsRefused(): void
	{
		try {
			OZone::inScope(app()->getScope(self::PLUGGED), static fn(): bool => true);

			self::fail('The scope was entered.');
		} catch (RuntimeException $e) {
			self::assertStringContainsString('"oz.plugins"', $e->getMessage());
			self::assertStringContainsString('"' . self::PLUGGED . '"', $e->getMessage());
		}

		self::assertCallersScope();

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('~"oz\.plugins"~');

		Context::root()->callAnonymously('oz:robots', [], [], self::PLUGGED);
	}

	/**
	 * Entering a scope runs its settings files, the first time, and one of them may switch scope in
	 * turn: a switch back into the scope from there finds settings half put together, and building
	 * them again from inside their own build never ends. It is refused, naming the scope, every time,
	 * since half-built settings are never kept as the scope's.
	 */
	public function testAScopeAskedForWhileItsSettingsArePutTogetherIsRefused(): void
	{
		for ($attempt = 0; $attempt < 2; ++$attempt) {
			try {
				OZone::inScope(app()->getScope(self::REENTRANT), static fn(): bool => true);

				self::fail('The scope was entered.');
			} catch (RuntimeException $e) {
				self::assertSame(
					'The settings of the scope "' . self::REENTRANT . '" were asked for while they were being'
						. ' put together.',
					$e->getMessage()
				);
			}

			self::assertCallersScope();
		}
	}

	/**
	 * A read of a scope's settings wires nothing, so a scope this process cannot serve -- logs of
	 * its own -- still has a server name for a deploy; and the settings that read put together are
	 * compared the first time a call enters them, which is still refused naming the group.
	 */
	public function testAReadOfASettingIsAllowedWhereACallIsNot(): void
	{
		self::assertSame('logged.example.test', DeployInitializer::serverName(self::LOGGED));
		self::assertCallersScope();

		try {
			Context::root()->callAnonymously('oz:robots', [], [], self::LOGGED);

			self::fail('The call entered a scope with logs of its own.');
		} catch (RuntimeException $e) {
			self::assertStringContainsString('"oz.logs"', $e->getMessage());
		}

		self::assertCallersScope();

		// And read again after the refusal.
		self::assertSame('logged.example.test', DeployInitializer::serverName(self::LOGGED));
	}

	/**
	 * Inside a read, nothing that acts on a scope may run: the scope in use has its settings and none
	 * of the rest.
	 */
	public function testNoScopeIsEnteredInsideARead(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains(
			'The scope "' . self::SCOPE . '" cannot be entered while only the settings of a scope are being read.'
		);

		OZone::inScope(
			app()->getScope(self::LOGGED),
			static fn(): bool => OZone::inScope(app()->getScope(self::SCOPE), static fn(): bool => true),
			true
		);
	}

	/**
	 * A class of the called scope lives in the scope's own directory, as for a request the scope
	 * serves: the call finds it.
	 */
	public function testAClassOfTheCalledScopesOwnDirectoryIsFound(): void
	{
		$response = Context::root()->callAnonymously('anon:scoped', [], [], self::SCOPE);

		self::assertSame(200, $response->getStatusCode());
		self::assertSame('yes', $response->getHeaderLine('X-Scoped'));
	}

	public function testACallInsideAnOpenTransactionIsRefused(): void
	{
		db()->beginTransaction();

		try {
			$this->expectException(RuntimeException::class);
			$this->expectExceptionMessageMatches('~inside an open transaction~');

			Context::root()->callAnonymously('anon:where', [], [], self::SCOPE);
		} finally {
			db()->rollBack();
		}
	}

	public function testTheKindOfAScopeIsItsRecordsAndTheServedOnesTheProcesss(): void
	{
		self::assertSame(ScopeKind::API, ScopeKind::of(app()->getScope(self::SCOPE)));
		self::assertSame(ScopeKind::ofProcess(), ScopeKind::of(scope()));
	}

	/**
	 * Back to the scope the suite serves, with its own settings.
	 */
	private static function assertCallersScope(): void
	{
		self::assertSame(ScopeInterface::ROOT_SCOPE, scope()->getName());
		self::assertSame('app', Settings::get(AnonymousCallRoutes::PROBE_GROUP, 'WHERE'));
	}

	/**
	 * A request of the suite signed in as a user, through its route's authentication.
	 */
	private static function signedInCaller(string $user_id): Context
	{
		$router = new Router();
		$router->get('/caller', static fn() => null)
			->name('test:anon.caller')
			->withAuthentication(HeaderTestAuth::class);

		$context = new Context(
			HTTPEnvironment::mock(['HTTP_' . \strtoupper(\str_replace('-', '_', HeaderTestAuth::HEADER)) => $user_id]),
			null,
			Context::root()
		);

		$authenticate = static function (RouteInfo $ri) use ($context): void {
			$context->authState()->authenticate($ri);
		};

		new RouteInfo($context, $router->requireRoute('test:anon.caller'), [], $authenticate);

		return $context;
	}

	/**
	 * The class name of the provider living in the called scope's directory, in the project namespace.
	 */
	private static function scopedProvider(): string
	{
		return \trim((string) Settings::get('oz.config', 'OZ_PROJECT_NAMESPACE'), '\\') . '\\' . self::SCOPED_PROVIDER;
	}

	private static function sessionCount(): int
	{
		return new OZSessionsQuery()->find(ORMOptions::makePaginated(1))->getTotal();
	}

	/**
	 * A scope of the sandbox project: its directory, its recorded kind, its settings.
	 *
	 * @param array<string, array> $settings
	 */
	private static function makeScope(string $name, array $settings): void
	{
		$dir = self::scopeDir($name);

		foreach (['settings', 'templates'] as $sub) {
			if (!\is_dir($dir . '/' . $sub)) {
				\mkdir($dir . '/' . $sub, 0o775, true);
			}
		}

		ScopeKind::API->write(app()->getProjectDir()->getRoot(), $name);

		self::writeSettings($dir . '/settings', $settings);
	}

	/**
	 * @param array<string, array> $settings
	 */
	private static function writeSettings(string $dir, array $settings): void
	{
		foreach ($settings as $group => $values) {
			\file_put_contents(
				$dir . '/' . $group . '.php',
				"<?php\n\ndeclare(strict_types=1);\n\nreturn " . \var_export($values, true) . ";\n"
			);
		}
	}

	private static function remove(string $path): void
	{
		if (\is_link($path) || \is_file($path)) {
			\unlink($path);

			return;
		}

		if (!\is_dir($path)) {
			return;
		}

		foreach (\scandir($path) ?: [] as $entry) {
			if ('.' !== $entry && '..' !== $entry) {
				self::remove($path . '/' . $entry);
			}
		}

		\rmdir($path);
	}

	private static function scopeDir(string $name): string
	{
		return app()->getProjectDir()->resolve('scopes') . '/' . $name;
	}
}
