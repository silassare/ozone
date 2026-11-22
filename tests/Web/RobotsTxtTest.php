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

namespace OZONE\Tests\Web;

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\OZone;
use OZONE\Core\Web\DefaultRobotsTxtProvider;
use OZONE\Core\Web\Interfaces\RobotsTxtProviderInterface;
use OZONE\Core\Web\RobotsTxt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Class RobotsTxtTest.
 *
 * What `/robots.txt` answers: OZone's default, or what the provider a project names says for the
 * request being answered.
 *
 * @internal
 */
#[CoversClass(RobotsTxt::class)]
#[CoversClass(DefaultRobotsTxtProvider::class)]
final class RobotsTxtTest extends TestCase
{
	#[Override]
	protected function tearDown(): void
	{
		Settings::unset('oz.request', 'OZ_ROBOTS_TXT_PROVIDER');
	}

	public function testTheDefaultKeepsEveryCrawlerOutOfEverything(): void
	{
		$response = RobotsTxt::answer(self::context());

		self::assertSame(200, $response->getStatusCode());
		self::assertSame('text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
		self::assertSame("User-agent: *\nDisallow: /\n", (string) $response->getBody());
		// Whatever the scope or the host: the default has no reason to tell them apart.
		self::assertSame(
			"User-agent: *\nDisallow: /\n",
			(string) RobotsTxt::answer(self::context('www.example.com', false))->getBody()
		);
	}

	public function testTheProviderTheSettingNamesAnswers(): void
	{
		Settings::set('oz.request', 'OZ_ROBOTS_TXT_PROVIDER', RobotsTxtTestProvider::class);

		$response = RobotsTxt::answer(self::context('www.example.com', false));

		self::assertSame(200, $response->getStatusCode());
		self::assertSame('text/plain; charset=utf-8', $response->getHeaderLine('Content-Type'));
		self::assertSame(
			"User-agent: *\nAllow: /\nSitemap: https://www.example.com/sitemap.xml\n",
			(string) $response->getBody()
		);
	}

	public function testTheProviderAnswersPerScopeAndPerHost(): void
	{
		Settings::set('oz.request', 'OZ_ROBOTS_TXT_PROVIDER', RobotsTxtTestProvider::class);

		$www  = (string) RobotsTxt::answer(self::context('www.example.com', false))->getBody();
		$shop = (string) RobotsTxt::answer(self::context('shop.example.com', false))->getBody();
		$api  = (string) RobotsTxt::answer(self::context('www.example.com', true))->getBody();

		self::assertStringContainsString('Sitemap: https://www.example.com/sitemap.xml', $www);
		self::assertStringContainsString('Sitemap: https://shop.example.com/sitemap.xml', $shop);
		// The API scope serving the same host keeps every crawler out, as the provider decided.
		self::assertSame("# scope root\nUser-agent: *\nDisallow: /\n", $api);
	}

	public function testAClassThatIsNoProviderIsRefusedNamingTheSetting(): void
	{
		Settings::set('oz.request', 'OZ_ROBOTS_TXT_PROVIDER', stdClass::class);

		try {
			RobotsTxt::answer(self::context());
			self::fail('A class that is no robots.txt provider was used.');
		} catch (RuntimeException $e) {
			self::assertStringContainsString('"stdClass" should implement', $e->getMessage());
			self::assertSame(
				['type' => 'config', 'group' => 'oz.request', 'key' => 'OZ_ROBOTS_TXT_PROVIDER'],
				$e->getData(true)['_suspect'] ?? null
			);
		}
	}

	public function testTheRouteIsMappedInTheApiAndTheWebScopes(): void
	{
		foreach ([OZone::getApiRouter(), OZone::getWebRouter()] as $router) {
			$route = $router->getRoute(RobotsTxt::ROUTE);

			self::assertNotNull($route);
			self::assertSame(RobotsTxt::PATH, $route->getPath());
			self::assertSame(['GET'], $route->getMethods());
		}
	}

	/**
	 * A request for `/robots.txt` on a host, served by an API or a web scope.
	 */
	private static function context(string $host = 'localhost', bool $api = true): Context
	{
		return new Context(HTTPEnvironment::mock([
			'REQUEST_URI' => RobotsTxt::PATH,
			'HTTP_HOST'   => $host,
		]), null, Context::root(), $api);
	}
}

/**
 * A provider answering as a site's would: crawlers welcome on a web scope, with the sitemap of the
 * host served; kept out of an API scope, which it marks with the scope's name.
 *
 * @internal
 */
final class RobotsTxtTestProvider implements RobotsTxtProviderInterface
{
	#[Override]
	public function robotsTxt(Context $context): string
	{
		if ($context->isApiContext()) {
			return '# scope ' . scope()->getName() . "\n" . DefaultRobotsTxtProvider::CONTENT;
		}

		return "User-agent: *\nAllow: /\nSitemap: https://" . $context->getHost() . "/sitemap.xml\n";
	}
}
