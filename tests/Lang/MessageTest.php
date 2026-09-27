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

use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Lang\Message\MessageParser;
use OZONE\Core\Lang\Message\MessageRenderer;
use OZONE\Core\Lang\Message\MessageSyntaxException;
use OZONE\Core\Lang\Message\PluralRules;
use PHPUnit\Framework\TestCase;

/**
 * The message syntax of the catalogs, parsed and written, with and without plural rules.
 *
 * @internal
 *
 * @covers \OZONE\Core\Lang\Message\MessageParser
 * @covers \OZONE\Core\Lang\Message\MessageRenderer
 * @covers \OZONE\Core\Lang\Message\PluralRules
 */
final class MessageTest extends TestCase
{
	/**
	 * @dataProvider provideWritesAsTheSyntaxSaysCases
	 *
	 * @param array<string, mixed> $data
	 */
	public function testWritesAsTheSyntaxSays(string $text, array $data, string $expected): void
	{
		self::assertSame($expected, self::render($text, $data, 'en', true));
	}

	/**
	 * @return iterable<string, array{string, array<string, mixed>, string}>
	 */
	public static function provideWritesAsTheSyntaxSaysCases(): iterable
	{
		yield 'plain text' => ['Hello.', [], 'Hello.'];

		yield 'a value' => ['Hello {name}.', ['name' => 'Ann'], 'Hello Ann.'];

		yield 'spaces inside' => ['Hello { name }.', ['name' => 'Ann'], 'Hello Ann.'];

		yield 'a missing value writes nothing' => ['Hello {name}.', [], 'Hello .'];

		yield 'a path' => ['{user.first} {user.last}', ['user' => ['first' => 'Ann', 'last' => 'Lee']], 'Ann Lee'];

		yield 'a numeric name' => ['{0} and {1}', ['a', 'b'], 'a and b'];

		yield 'values as PHP writes them' => [
			'{t}|{f}|{n}|{i}|{x}|{a}',
			['t' => true, 'f' => false, 'n' => null, 'i' => 3, 'x' => 1.5, 'a' => [1]],
			'1|||3|1.5|',
		];

		yield 'a value is never read for placeholders' => [
			'{a} and {b}',
			['a' => '{b}', 'b' => 'x'],
			'{b} and x',
		];

		yield 'filters chain' => ['{name | lower | capitalize}', ['name' => 'ÉMILE'], 'Émile'];

		yield 'upper' => ['{name | upper}', ['name' => 'straße'], 'STRASSE'];

		yield 'escapes' => ['\{name\} \\\\ \q #', ['name' => 'x'], '{name} \ \q #'];

		yield 'plural, exact' => ['{n, plural, =0 {none} one {# file} other {# files}}', ['n' => 0], 'none'];

		yield 'plural, category' => ['{n, plural, =0 {none} one {# file} other {# files}}', ['n' => 1], '1 file'];

		yield 'plural, other' => ['{n, plural, =0 {none} one {# file} other {# files}}', ['n' => 7], '7 files'];

		yield 'plural, a comparison' => ['{n, plural, >=10 {many} other {#}}', ['n' => 12], 'many'];

		yield 'plural, the order written' => ['{n, plural, >0 {positive} =1 {one} other {x}}', ['n' => 1], 'positive'];

		yield 'plural, other anywhere' => ['{n, plural, other {#} =2 {two}}', ['n' => 2], 'two'];

		yield 'plural, a number as text' => ['{n, plural, =3 {three} other {x}}', ['n' => '3'], 'three'];

		yield 'plural, not a number' => ['{n, plural, =3 {three} other {[#]}}', ['n' => 'abc'], '[abc]'];

		yield 'plural, a boolean' => ['{n, plural, =1 {one} other {[#]}}', ['n' => true], '[1]'];

		yield 'plural, an escaped hash' => ['{n, plural, other {\# #}}', ['n' => 4], '# 4'];

		yield 'select' => ['{g, select, female {she} male {he} other {they}}', ['g' => 'female'], 'she'];

		yield 'select, other' => ['{g, select, female {she} male {he} other {they}}', ['g' => 'x'], 'they'];

		yield 'a choice inside a branch' => [
			'{g, select, female {{n, plural, one {her file} other {her # files}}} other {# files}}',
			['g' => 'female', 'n' => 2],
			'her 2 files',
		];

		yield 'a hash outside a plural is text' => ['{g, select, other {# {g}}}', ['g' => 'x'], '# x'];
	}

	public function testCategoriesComeFromTheLanguageRules(): void
	{
		// ext-intl is in the test image: without it, these cases would not test the rules at all.
		self::assertTrue(PluralRules::available(), 'ext-intl must be loaded to test the plural rules.');

		$ordinal = '{d, selectordinal, one {#st} two {#nd} few {#rd} other {#th}}';

		$ordinals = [
			1   => '1st',
			2   => '2nd',
			3   => '3rd',
			4   => '4th',
			11  => '11th',
			21  => '21st',
			22  => '22nd',
			23  => '23rd',
			111 => '111th',
		];

		foreach ($ordinals as $d => $expected) {
			self::assertSame($expected, self::render($ordinal, ['d' => $d], 'en', true));
		}

		$arabic = '{n, plural, zero {z} one {o} two {t} few {f} many {m} other {x}}';

		$forms = [0 => 'z', 1 => 'o', 2 => 't', 3 => 'f', 10 => 'f', 11 => 'm', 99 => 'm', 100 => 'x'];

		foreach ($forms as $n => $expected) {
			self::assertSame($expected, self::render($arabic, ['n' => $n], 'ar', true), "ar {$n}");
		}

		self::assertSame('o', self::render($arabic, ['n' => 1], 'fr', true));
		self::assertSame('o', self::render($arabic, ['n' => 0], 'fr', true), 'French counts 0 as one');
	}

	public function testWithoutRulesACategoryNeverMatches(): void
	{
		$text = '{n, plural, =0 {none} one {one} other {#}}';

		self::assertSame('1', self::render($text, ['n' => 1], 'en', false));
		self::assertSame('none', self::render($text, ['n' => 0], 'en', false));
		self::assertSame(
			'1st',
			self::render('{d, selectordinal, =1 {#st} other {#th}}', ['d' => 1], 'en', false)
		);
	}

	public function testANumberFilterFormatsForTheLanguage(): void
	{
		self::assertSame('1,234.50', self::render('{n | number: 2}', ['n' => 1234.5], 'en', true));
		self::assertSame('abc', self::render('{n | number: 2}', ['n' => 'abc'], 'en', true));
	}

	/**
	 * @dataProvider provideADateFilterWritesAnInstantInUtcCases
	 *
	 * @param array<string, mixed> $data
	 */
	public function testADateFilterWritesAnInstantInUtc(string $text, array $data, string $lang, string $expected): void
	{
		// ICU writes a narrow no-break space before "PM"
		self::assertSame($expected, \str_replace("\u{202F}", ' ', self::render($text, $data, $lang, true)));
	}

	/**
	 * @return iterable<string, array{string, array<string, mixed>, string, string}>
	 */
	public static function provideADateFilterWritesAnInstantInUtcCases(): iterable
	{
		// 2026-09-21 14:13:20 UTC
		$t = 1790000000;

		yield 'a medium date by default' => ['{d | date}', ['d' => $t], 'en', 'Sep 21, 2026'];

		yield 'a long date' => ['{d | date: "long"}', ['d' => $t], 'en', 'September 21, 2026'];

		yield 'a date and a time' => ['{d | date: "short", "short"}', ['d' => $t], 'en', '9/21/26, 2:13 PM'];

		yield 'a time alone' => ['{d | date: "none", "short"}', ['d' => $t], 'en', '2:13 PM'];

		yield 'in the language' => ['{d | date: "long"}', ['d' => $t], 'fr', '21 septembre 2026'];

		yield 'the text of a timestamp' => ['{d | date}', ['d' => '1790000000'], 'en', 'Sep 21, 2026'];

		yield 'a fraction of a second is dropped' => ['{d | date: "none", "medium"}', ['d' => $t + 0.99], 'en', '2:13:20 PM'];

		yield 'before 1970' => ['{d | date: "long"}', ['d' => -86400], 'en', 'December 31, 1969'];

		yield 'an ISO date, at midnight UTC' => ['{d | date: "short", "short"}', ['d' => '2026-09-21'], 'en', '9/21/26, 12:00 AM'];

		yield 'an ISO time without offset is UTC' => ['{d | date: "none", "short"}', ['d' => '2026-09-21T14:13'], 'en', '2:13 PM'];

		yield 'an ISO time with an offset' => ['{d | date: "short", "short"}', ['d' => '2026-09-21T01:30:00+02:00'], 'en', '9/20/26, 11:30 PM'];

		yield 'a day past its month rolls over' => ['{d | date}', ['d' => '2026-02-30'], 'en', 'Mar 2, 2026'];

		yield 'not an instant, as it is' => ['{d | date}', ['d' => 'soon'], 'en', 'soon'];

		yield 'an impossible month, as it is' => ['{d | date}', ['d' => '2026-13-01'], 'en', '2026-13-01'];

		yield 'out of range, as it is' => ['{d | date}', ['d' => 1e21], 'en', '1.0E+21'];
	}

	/**
	 * @dataProvider provideADateFilterRefusesUnknownStylesCases
	 */
	public function testADateFilterRefusesUnknownStyles(string $text): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Invalid date filter styles');

		self::render($text, ['d' => 1790000000], 'en', true);
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function provideADateFilterRefusesUnknownStylesCases(): iterable
	{
		yield 'an unknown date style' => ['{d | date: "tiny"}'];

		yield 'an unknown time style' => ['{d | date: "short", "tiny"}'];

		yield 'nothing to write' => ['{d | date: "none", "none"}'];
	}

	public function testAProjectFilterTakesTheLanguageThenItsArguments(): void
	{
		$filters = ['wrap' => static fn (mixed $v, string $lang, string $l, string $r) => $l . $v . $r . $lang];

		self::assertSame(
			'[x]fr',
			self::render('{v | wrap: "[", "]"}', ['v' => 'x'], 'fr', true, $filters)
		);
		self::assertSame(
			'a"b\\',
			self::render('{v | wrap: "a", "\"b\\\\"}', ['v' => ''], '', true, [
				'wrap' => static fn (mixed $v, string $lang, string $l, string $r) => $l . $r,
			])
		);
	}

	public function testAnUnknownFilterIsAnError(): void
	{
		$this->expectException(RuntimeException::class);

		self::render('{v | nope}', ['v' => 'x'], 'en', true);
	}

	public function testIncludesAnotherKeyWithTheSameData(): void
	{
		$texts = ['SIGN' => 'regards, {name}', 'LOOP' => 'a {{LOOP}}'];
		$include = static fn (string $key): array => MessageParser::parse($texts[$key] ?? $key);
		$renderer = new MessageRenderer('en', $include, [], null);

		self::assertSame(
			'Hi, regards, Ann',
			$renderer->render(MessageParser::parse('Hi, {{ SIGN }}'), ['name' => 'Ann'])
		);

		$this->expectException(RuntimeException::class);

		$renderer->render(MessageParser::parse('{{LOOP}}'), []);
	}

	/**
	 * @dataProvider provideWritesAsHtmlCases
	 *
	 * @param array<string, mixed> $data
	 */
	public function testWritesAsHtml(string $text, array $data, string $expected): void
	{
		$texts    = ['BOLD' => '<b>{name}</b>'];
		$renderer = new MessageRenderer(
			'en',
			static fn (string $key): array => MessageParser::parse($texts[$key] ?? $key),
			['wrap' => static fn (mixed $value): string => '<' . $value . '>'],
			PluralRules::category(...),
			true
		);

		self::assertSame($expected, $renderer->render(MessageParser::parse($text), $data));
	}

	/**
	 * @return iterable<string, array{string, array<string, mixed>, string}>
	 */
	public static function provideWritesAsHtmlCases(): iterable
	{
		yield 'the text as it is' => ['Read the <a href="/terms">terms</a>.', [], 'Read the <a href="/terms">terms</a>.'];

		yield 'a value escaped' => ['Hi <b>{name}</b>', ['name' => '<i>Ann</i> & "Bo" \'x\''], 'Hi <b>&lt;i&gt;Ann&lt;/i&gt; &amp; &quot;Bo&quot; &#039;x&#039;</b>'];

		yield 'already escaped, escaped again' => ['{v}', ['v' => '&amp;'], '&amp;amp;'];

		yield 'after its filters' => ['{v | upper}', ['v' => '<i>'], '&lt;I&gt;'];

		yield "a project filter's markup" => ['{v | wrap}', ['v' => 'x'], '&lt;x&gt;'];

		yield 'a path' => ['{u.name}', ['u' => ['name' => '<x>']], '&lt;x&gt;'];

		yield 'the # of a plural' => ['{n, plural, =1 {<b>#</b>} other {#}}', ['n' => '<5>'], '&lt;5&gt;'];

		yield 'a branch keeps its markup' => ['{n, plural, =1 {<b>#</b>} other {#}}', ['n' => 1], '<b>1</b>'];

		yield 'an included text keeps its markup' => ['{{BOLD}}!', ['name' => '<i>'], '<b>&lt;i&gt;</b>!'];

		yield 'a value that is not text' => ['{v}{w}', ['v' => true, 'w' => ['x']], '1'];
	}

	/**
	 * @dataProvider provideRefusesWhatIsNotTheSyntaxCases
	 */
	public function testRefusesWhatIsNotTheSyntax(string $text): void
	{
		$this->expectException(MessageSyntaxException::class);

		MessageParser::parse($text);
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function provideRefusesWhatIsNotTheSyntaxCases(): iterable
	{
		yield 'an open brace' => ['a { b'];

		yield 'a lone closing brace' => ['a } b'];

		yield 'two names' => ['{a b}'];

		yield 'an empty name' => ['{}'];

		yield 'a bad path' => ['{a..b}'];

		yield 'a bad filter' => ['{a | 1x}'];

		yield 'a bad filter argument' => ['{a | f: x}'];

		yield 'an unterminated string' => ['{a | f: "x}'];

		yield 'a lower-case include' => ['{{key}}'];

		yield 'an unknown choice' => ['{n, weird, other {x}}'];

		yield 'no other branch' => ['{n, plural, one {x}}'];

		yield 'a bad selector' => ['{n, plural, lots {x} other {y}}'];

		yield 'an unterminated branch' => ['{n, plural, other {x}'];
	}

	/**
	 * @param array<string, mixed>    $data
	 * @param array<string, callable> $filters
	 */
	private static function render(string $text, array $data, string $lang, bool $rules, array $filters = []): string
	{
		$renderer = new MessageRenderer(
			$lang,
			static fn (string $key): array => MessageParser::parse($key),
			$filters,
			$rules ? PluralRules::category(...) : null
		);

		return $renderer->render(MessageParser::parse($text), $data);
	}
}
