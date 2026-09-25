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
