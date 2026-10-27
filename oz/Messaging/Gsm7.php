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

namespace OZONE\Core\Messaging;

/**
 * Class Gsm7.
 *
 * The GSM 03.38 alphabet of SMS. A text written in it takes 160 characters per message; a single
 * character outside it switches the whole text to UCS-2, 70 characters per message, which can triple
 * what it costs. {@see fold()} keeps a text inside it (a channel's `gsm7` option applies it to every
 * text it sends), {@see segments()} counts the messages a text takes.
 */
final class Gsm7
{
	/**
	 * The basic character set (the escape character excepted).
	 */
	public const string BASIC = "@\u{a3}\$\u{a5}\u{e8}\u{e9}\u{f9}\u{ec}\u{f2}\u{c7}\n\u{d8}\u{f8}\r\u{c5}\u{e5}"
		. "\u{394}_\u{3a6}\u{393}\u{39b}\u{3a9}\u{3a0}\u{3a8}\u{3a3}\u{398}\u{39e}\u{c6}\u{e6}\u{df}\u{c9}"
		. " !\"#\u{a4}%&'()*+,-./0123456789:;<=>?"
		. "\u{a1}ABCDEFGHIJKLMNOPQRSTUVWXYZ\u{c4}\u{d6}\u{d1}\u{dc}\u{a7}"
		. "\u{bf}abcdefghijklmnopqrstuvwxyz\u{e4}\u{f6}\u{f1}\u{fc}\u{e0}";

	/**
	 * The extension table: each of these takes two characters of a message (an escape, then itself).
	 */
	public const string EXTENSION = "\f^{}\\[~]|\u{20ac}";

	/**
	 * Characters per message, for one message and for each part of a longer one.
	 */
	public const int GSM7_SINGLE = 160;
	public const int GSM7_PART   = 153;
	public const int UCS2_SINGLE = 70;
	public const int UCS2_PART   = 67;

	/**
	 * What a character outside the alphabet becomes when it has a close one inside it.
	 *
	 * @var array<string, string>
	 */
	private const array FOLD = [
		// Latin letters with marks the alphabet does not have.
		'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ā' => 'a', 'ă' => 'a', 'ą' => 'a',
		'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ā' => 'A', 'Ă' => 'A', 'Ą' => 'A',
		'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'Ć' => 'C', 'Č' => 'C',
		'ď' => 'd', 'đ' => 'd', 'Ď' => 'D', 'Đ' => 'D',
		'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ė' => 'e', 'ę' => 'e', 'ě' => 'e',
		'È' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ē' => 'E', 'Ė' => 'E', 'Ę' => 'E', 'Ě' => 'E',
		'ğ' => 'g', 'Ğ' => 'G',
		'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'į' => 'i', 'ı' => 'i',
		'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ī' => 'I', 'Į' => 'I', 'İ' => 'I',
		'ł' => 'l', 'Ł' => 'L',
		'ń' => 'n', 'ň' => 'n', 'Ń' => 'N', 'Ň' => 'N',
		'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ō' => 'o', 'ő' => 'o',
		'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ō' => 'O', 'Ő' => 'O',
		'ř' => 'r', 'Ř' => 'R',
		'ś' => 's', 'š' => 's', 'ş' => 's', 'Ś' => 'S', 'Š' => 'S', 'Ş' => 'S',
		'ť' => 't', 'ţ' => 't', 'Ť' => 'T', 'Ţ' => 'T',
		'ú' => 'u', 'û' => 'u', 'ū' => 'u', 'ů' => 'u', 'ű' => 'u', 'ų' => 'u',
		'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ū' => 'U', 'Ů' => 'U', 'Ű' => 'U', 'Ų' => 'U',
		'ý' => 'y', 'ÿ' => 'y', 'Ý' => 'Y', 'Ÿ' => 'Y',
		'ź' => 'z', 'ż' => 'z', 'ž' => 'z', 'Ź' => 'Z', 'Ż' => 'Z', 'Ž' => 'Z',
		'œ' => 'oe', 'Œ' => 'OE', 'ĳ' => 'ij', 'Ĳ' => 'IJ',
		// Typography: what a word processor or a phone keyboard puts in place of the plain signs.
		"\u{2018}" => "'", "\u{2019}" => "'", "\u{201a}" => "'", "\u{2032}" => "'", '`' => "'",
		"\u{201c}" => '"', "\u{201d}" => '"', "\u{201e}" => '"', "\u{2033}" => '"',
		"\u{ab}"   => '"', "\u{bb}" => '"',
		"\u{2010}" => '-', "\u{2011}" => '-', "\u{2012}" => '-', "\u{2013}" => '-', "\u{2014}" => '-',
		"\u{2015}" => '-', "\u{2212}" => '-',
		"\u{2026}" => '...', "\u{2022}" => '*', "\u{b7}" => '.',
		"\u{a0}"   => ' ', "\u{202f}" => ' ', "\u{2009}" => ' ', "\u{2007}" => ' ', "\t" => ' ',
	];

	/**
	 * What a character with no close one in the alphabet becomes.
	 */
	private const string UNKNOWN = '?';

	/**
	 * Whether every character of the text is in the alphabet (its extension table included).
	 */
	public static function fits(string $text): bool
	{
		return \array_all(\mb_str_split($text), self::inAlphabet(...));
	}

	/**
	 * The text with every character outside the alphabet replaced by its closest one inside it, or
	 * by `?` when it has none.
	 */
	public static function fold(string $text): string
	{
		$out = '';

		foreach (\mb_str_split($text) as $char) {
			if (self::inAlphabet($char)) {
				$out .= $char;
			} else {
				$out .= self::FOLD[$char] ?? self::UNKNOWN;
			}
		}

		return $out;
	}

	/**
	 * How many SMS the text takes: 0 when it is empty.
	 */
	public static function segments(string $text): int
	{
		if ('' === $text) {
			return 0;
		}

		if (self::fits($text)) {
			$length = 0;

			foreach (\mb_str_split($text) as $char) {
				$length += \str_contains(self::EXTENSION, $char) ? 2 : 1;
			}

			return $length <= self::GSM7_SINGLE ? 1 : (int) \ceil($length / self::GSM7_PART);
		}

		// UTF-16 code units: a character beyond the basic plane (an emoji) takes two.
		$length = \intdiv(\strlen((string) \mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')), 2);

		return $length <= self::UCS2_SINGLE ? 1 : (int) \ceil($length / self::UCS2_PART);
	}

	private static function inAlphabet(string $char): bool
	{
		return \str_contains(self::BASIC, $char) || \str_contains(self::EXTENSION, $char);
	}
}
