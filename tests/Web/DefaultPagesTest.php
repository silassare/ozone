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

use Gobl\DBAL\Types\TypeString;
use OZONE\Core\App\Context;
use OZONE\Core\Columns\Types\TypePassword;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\InternalErrorException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\Views\ErrorView;
use OZONE\Core\Forms\Form;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Router\Views\AccessGrantView;
use OZONE\Core\Web\WebView;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * The default pages: every text translated, every page on the shared layout and stylesheet.
 *
 * @internal
 *
 * @covers \OZONE\Core\Exceptions\Views\ErrorView
 * @covers \OZONE\Core\Router\Views\AccessGrantView
 */
final class DefaultPagesTest extends TestCase
{
	public function testAnErrorPageIsTitledByItsStatusAndTranslated(): void
	{
		$en = self::error(new NotFoundException(), []);
		$fr = self::error(new NotFoundException(), ['HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9']);

		self::assertStringContainsString('<title>Page not found</title>', $en);
		self::assertStringContainsString('Error 404', $en);
		self::assertStringContainsString('The requested resource was not found.', $en);
		self::assertStringContainsString('<title>Page introuvable</title>', $fr);
		self::assertStringContainsString('Erreur 404', $fr);
		self::assertStringContainsString('<html lang="fr" dir="ltr">', $fr);

		self::assertStringContainsString('<title>Access denied</title>', self::error(new ForbiddenException(), []));
	}

	public function testAnInternalErrorSaysNothingOfItsCause(): void
	{
		$page = self::error(new InternalErrorException('Database password is hunter2.'), []);

		self::assertStringContainsString('An internal error occurred.', $page);
		self::assertStringNotContainsString('<p class="oz-card__text">Database password', $page);
	}

	public function testATitleKeyExistsForEveryStatusThatHasOne(): void
	{
		self::assertSame('OZ_VIEW_ERROR_TITLE_404', ErrorView::titleKey(404));
		self::assertSame('OZ_VIEW_ERROR_TITLE', ErrorView::titleKey(418));
	}

	public function testEveryPageUsesTheLayoutAndTheTheme(): void
	{
		$page = self::error(new NotFoundException(), []);

		self::assertStringContainsString('<meta name="viewport" content="width=device-width, initial-scale=1">', $page);
		self::assertStringNotContainsString('user-scalable', $page);
		self::assertStringNotContainsString('fonts.googleapis.com', $page);
		self::assertStringContainsString('--oz-color-primary:', $page);
		self::assertStringContainsString('class="oz-card__mark"', $page);
	}

	/**
	 * The confirmation page needs a matched route for its CSRF token: tested on a server
	 * (`DefaultPagesServedTest`).
	 */
	public function testTheAuthLinkResultPages(): void
	{
		$done    = self::template('oz.auth.link.view.blate', ['oz_auth_link_done' => true]);
		$invalid = self::template('oz.auth.link.view.blate', ['oz_auth_link_done' => false]);

		self::assertStringContainsString('Confirmed', $done);
		self::assertStringContainsString('oz-badge--success', $done);
		self::assertStringContainsString('This link no longer works', $invalid);
		self::assertStringContainsString('oz-badge--danger', $invalid);
	}

	/**
	 * A page that ends a flow links onward when `OZ_AUTH_SIGN_IN_ROUTE` gave a URI, and says its
	 * piece without one when it did not: the person who just changed their password is not sent back
	 * to the email to find where to sign in.
	 */
	public function testTheEndOfAFlowLinksToTheSignInPageWhenThereIsOne(): void
	{
		$recovered = self::template('oz.auth.link.recovery.blate', [
			'oz_recovery_done' => true,
			'oz_sign_in_uri'   => 'https://acme.test/sign-in',
		]);
		$alone = self::template('oz.auth.link.recovery.blate', [
			'oz_recovery_done' => true,
			'oz_sign_in_uri'   => '',
		]);
		$confirmed = self::template('oz.auth.link.view.blate', [
			'oz_auth_link_done' => true,
			'oz_sign_in_uri'    => 'https://acme.test/sign-in',
		]);

		self::assertStringContainsString('Password changed', $recovered);
		self::assertStringContainsString(
			'<a class="oz-btn" href="https://acme.test/sign-in">Sign in</a>',
			$recovered
		);
		self::assertStringContainsString('Password changed', $alone);
		self::assertStringNotContainsString('<div class="oz-card__actions">', $alone);
		self::assertStringContainsString(
			'<a class="oz-btn" href="https://acme.test/sign-in">Sign in</a>',
			$confirmed
		);

		// The confirmation page says what its page offers: signing in, or closing the window.
		self::assertStringContainsString('You can now sign in.', $confirmed);
		self::assertStringContainsString(
			'You can close this window',
			self::template('oz.auth.link.view.blate', ['oz_auth_link_done' => true, 'oz_sign_in_uri' => ''])
		);
	}

	public function testTheAccessGrantFormLabelsItsFields(): void
	{
		$form = new Form();
		$form->field('grant_form_ref')
			->type((new TypeString())->default('ref-1'))
			->hidden()
			->required();
		$form->field('pass')
			->type(new TypePassword())
			->label('OZ_ACCESS_GRANT_FORM_PASSWORD')
			->required();

		$page = (string) (new AccessGrantView(self::context([])))->renderAccessGrantForm($form)->getBody();

		self::assertStringContainsString('<title>Sign in to continue</title>', $page);
		self::assertStringContainsString('<label class="oz-form__label" for="oz_field_pass">Password', $page);
		self::assertStringContainsString('type="password"', $page);
		self::assertStringContainsString('<input type="hidden" name="grant_form_ref" value="ref-1">', $page);
		self::assertStringNotContainsString('<input type="hidden" name="pass"', $page);
	}

	public function testTheAccessGrantAuthMessageNamesTheRealm(): void
	{
		$page = (string) (new AccessGrantView(self::context([])))
			->renderAccessGrantAuth(['scheme' => 'Basic', 'realm' => 'Staff area'])
			->getBody();

		self::assertStringContainsString('You need to sign in to access Staff area.', $page);
	}

	public function testTheWelcomePage(): void
	{
		$page = self::template('oz.welcome.blate', []);

		self::assertStringContainsString('The API is up and running.', $page);
		self::assertStringContainsString('Powered by OZone', $page);
	}

	private static function error(Throwable $e, array $env): string
	{
		return (string) (new ErrorView(self::context($env)))->renderError($e)->getBody();
	}

	private static function template(string $template, array $data): string
	{
		return (string) (new WebView(self::context([])))
			->setTemplate($template)
			->inject($data)
			->respond()
			->getBody();
	}

	private static function context(array $env): Context
	{
		return new Context(HTTPEnvironment::mock($env), null, Context::root());
	}
}
