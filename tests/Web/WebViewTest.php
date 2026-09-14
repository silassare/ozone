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

use OZONE\Core\App\Context;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Http\Response;
use OZONE\Core\Web\WebView;
use PHPUnit\Framework\TestCase;

/**
 * Class WebViewTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Web\WebView
 */
final class WebViewTest extends TestCase
{
	public function testRendersATemplateWithInjectedData(): void
	{
		$response = self::render([]);
		$body     = (string) $response->getBody();

		self::assertStringContainsString('https://example.com/next', $body);
		self::assertStringContainsString('You are being redirected.', $body);
		self::assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
	}

	public function testTranslatesInTheClientLanguage(): void
	{
		$body = (string) self::render(['HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9'])->getBody();

		self::assertStringContainsString('Vous allez être redirigé.', $body);
	}

	private static function render(array $env): Response
	{
		$context = new Context(HTTPEnvironment::mock($env), null, Context::root());

		return (new WebView($context))
			->setTemplate('oz.redirect.blate')
			->injectKey('oz_redirect_url', 'https://example.com/next')
			->respond();
	}
}
