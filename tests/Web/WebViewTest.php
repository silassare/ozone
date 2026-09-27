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

use Blate\Blate;
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

	public function testTheRedirectPageLinksToItsTarget(): void
	{
		$body = (string) self::render([])->getBody();

		// The link is the template's: a translation is plain text, escaped when printed.
		self::assertStringContainsString('<a href="https://example.com/next">Continue</a>', $body);
		self::assertStringNotContainsString('&lt;', $body);
	}

	public function testTranslatesInTheClientLanguage(): void
	{
		$body = (string) self::render(['HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9'])->getBody();

		self::assertStringContainsString('Vous allez être redirigé.', $body);
		self::assertStringContainsString('<a href="https://example.com/next">Continuer</a>', $body);
	}

	public function testATemplatePrintsATranslationAsTextOrAsHtml(): void
	{
		// a WebView registers the plugin and makes its context the one translations read
		self::render([]);

		$template = Blate::fromString(
			'{$t(\'OZ_ACCESS_RIGHT_DESCRIPTION\', $map(\'action\', v), \'en\')}|'
			. '{= $t_html(\'OZ_ACCESS_RIGHT_DESCRIPTION\', $map(\'action\', v), \'en\')}|'
			. '{= $t(\'OZ_ACCESS_RIGHT_DESCRIPTION\', $map(\'action\', v), \'en\')}'
		);

		self::assertSame(
			// as text, escaped by the template; as HTML, the values escaped by the translation; and
			// what the HTML helper prevents: raw text lets a value through as markup
			'Allows the &lt;b&gt;x&lt;/b&gt; action.|Allows the &lt;b&gt;x&lt;/b&gt; action.|Allows the <b>x</b> action.',
			$template->runGet(['v' => '<b>x</b>'])
		);
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
