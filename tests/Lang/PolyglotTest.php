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

namespace OZONE\Tests\Lang;

use OZONE\Core\Lang\Polyglot;
use PHPUnit\Framework\TestCase;

/**
 * Class PolyglotTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Lang\Polyglot
 */
final class PolyglotTest extends TestCase
{
	public function testIsLangKeyReturnsTrueForValidKeys(): void
	{
		self::assertTrue(Polyglot::isLangKey('MY_KEY'));
		self::assertTrue(Polyglot::isLangKey('WELCOME_MESSAGE'));
		self::assertTrue(Polyglot::isLangKey('OZ_ERROR_INTERNAL'));
		self::assertTrue(Polyglot::isLangKey('GROUP.SUBGROUP.KEY'));
		self::assertTrue(Polyglot::isLangKey('KEY123'));
		self::assertTrue(Polyglot::isLangKey('KEY_WITH_NUMBERS_42'));
	}

	public function testIsLangKeyReturnsFalseForInvalidKeys(): void
	{
		self::assertFalse(Polyglot::isLangKey(''));
		self::assertFalse(Polyglot::isLangKey('lowercase'));
		self::assertFalse(Polyglot::isLangKey('123STARTS_WITH_NUMBER'));
		self::assertFalse(Polyglot::isLangKey('has space'));
		self::assertFalse(Polyglot::isLangKey('has-hyphen'));
		self::assertFalse(Polyglot::isLangKey('_STARTS_WITH_UNDERSCORE'));
	}

	public function testParseBrowserLanguageWithNullReturnsEmptyLanguagesAndNullAdvice(): void
	{
		$result = Polyglot::parseBrowserLanguage(null);
		self::assertArrayHasKey('languages', $result);
		self::assertArrayHasKey('advice', $result);
		self::assertSame([], $result['languages']);
		self::assertNull($result['advice']);
	}

	public function testParseBrowserLanguageWithEmptyStringReturnsEmpty(): void
	{
		$result = Polyglot::parseBrowserLanguage('');
		self::assertSame([], $result['languages']);
		self::assertNull($result['advice']);
	}

	public function testParseBrowserLanguageExtractsSingleLanguage(): void
	{
		$result = Polyglot::parseBrowserLanguage('en');
		self::assertArrayHasKey('en', $result['languages']);
	}

	public function testParseBrowserLanguageExtractsMultipleLanguages(): void
	{
		$result = Polyglot::parseBrowserLanguage('fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7');
		$langs  = $result['languages'];

		self::assertArrayHasKey('fr-FR', $langs);
		self::assertArrayHasKey('fr', $langs);
		self::assertArrayHasKey('en-US', $langs);
		self::assertArrayHasKey('en', $langs);
	}

	public function testParseBrowserLanguageAssignsQuality1ForMissingQFactor(): void
	{
		$result = Polyglot::parseBrowserLanguage('en');
		self::assertSame(1, $result['languages']['en']);
	}

	public function testParseBrowserLanguageRespectsQuality(): void
	{
		$result = Polyglot::parseBrowserLanguage('en;q=0.8,fr;q=0.9');
		$langs  = $result['languages'];

		// fr should come before en after arsort
		$keys = \array_keys($langs);
		self::assertSame('fr', $keys[0]);
		self::assertSame('en', $keys[1]);
	}

	public function testDeclareFilterRegistersCallable(): void
	{
		Polyglot::declareFilter('test_upper', static fn ($v, $lang) => \strtoupper($v));
		// If no exception is thrown, the filter was registered successfully.
		// We rely on this in translate() tests.
		self::assertTrue(true);
	}

	public function testExportedCatalogsHoldWhatTranslateReads(): void
	{
		$export = Polyglot::exportCatalogs();

		self::assertSame(Polyglot::getDefaultLanguage(), $export['default']);
		self::assertSame(\array_keys(Polyglot::getEnabledLanguages()), $export['languages']);

		foreach ($export['languages'] as $lang) {
			self::assertSame(
				Polyglot::translate('OZ_ERROR_INVALID_FORM', null, $lang),
				$export['catalogs'][$lang]['OZ_ERROR_INVALID_FORM']
			);
		}
	}

	public function testExportedCatalogsNameTheDeclaredFilters(): void
	{
		Polyglot::declareFilter('test_export_lower', static fn ($v) => \strtolower((string) $v));

		self::assertContains('test_export_lower', Polyglot::exportCatalogs()['filters']);
	}

	public function testAValueIsNeverReadForPlaceholders(): void
	{
		// A value holding a placeholder is shown as it is: it neither pulls in another variable's
		// value nor fills itself again, which looped forever when it named itself.
		self::assertSame(
			'The fields {field_confirm} and pass2 must have the same value.',
			Polyglot::translate(
				'OZ_FIELD_SHOULD_HAVE_SAME_VALUE',
				['field' => '{field_confirm}', 'field_confirm' => 'pass2'],
				'en'
			)
		);
		self::assertSame(
			'Allows the {action} action.',
			Polyglot::translate('OZ_ACCESS_RIGHT_DESCRIPTION', ['action' => '{action}'], 'en')
		);
	}

	public function testABrowserGetsTheExactLanguageItAsksForFirst(): void
	{
		$enabled = ['fr' => true, 'fr-bj' => true, 'en' => true];

		self::assertSame('fr-bj', Polyglot::parseBrowserLanguage('fr-bj', $enabled)['advice']);
		self::assertSame('fr', Polyglot::parseBrowserLanguage('fr-ci', $enabled)['advice']);
		self::assertSame('en', Polyglot::parseBrowserLanguage('de;q=0.9, en;q=0.5', $enabled)['advice']);
	}

	public function testALanguageWithoutACatalogFallsBackToTheDefault(): void
	{
		self::assertSame(
			Polyglot::translate('OZ_ERROR_INVALID_FORM', null, Polyglot::getDefaultLanguage()),
			Polyglot::translate('OZ_ERROR_INVALID_FORM', null, 'xx')
		);
	}

	public function testChecksEveryTextOfTheCatalogs(): void
	{
		$check = Polyglot::checkCatalogs([
			'en' => [
				'OK'     => 'Hello {name}',
				'BROKEN' => 'Hello {name',
				'group'  => ['NESTED' => 'a } b'],
			],
			'fr' => ['OK' => 'Bonjour {name}'],
		]);

		self::assertSame(['BROKEN', 'group.NESTED'], \array_column($check['errors'], 'key'));
		self::assertSame(['en', 'en'], \array_column($check['errors'], 'lang'));
		self::assertFalse($check['categories']);
	}

	public function testTellsWhetherATextReliesOnPluralCategories(): void
	{
		$uses = static fn (string $text): bool => Polyglot::checkCatalogs(['en' => ['K' => $text]])['categories'];

		self::assertTrue($uses('{n, plural, one {a} other {b}}'));
		self::assertTrue($uses('{g, select, x {{n, selectordinal, few {a} other {b}}} other {c}}'));
		self::assertFalse($uses('{n, plural, =1 {a} >1 {b} other {c}}'));
		// A select's words are not categories.
		self::assertFalse($uses('{g, select, one {a} other {b}}'));
	}

	public function testOzonesOwnCatalogsFollowTheSyntax(): void
	{
		self::assertSame([], Polyglot::checkCatalogs()['errors']);
	}
}
