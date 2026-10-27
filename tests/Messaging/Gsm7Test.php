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

namespace OZONE\Tests\Messaging;

use OZONE\Core\Messaging\Gsm7;
use OZONE\Core\Messaging\RenderedMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class Gsm7Test.
 *
 * @internal
 */
#[CoversClass(Gsm7::class)]
#[CoversClass(RenderedMessage::class)]
final class Gsm7Test extends TestCase
{
	public function testFits(): void
	{
		// é, è, à, ù, ü, ñ, Ç and the extension table are in the alphabet.
		self::assertTrue(Gsm7::fits("Réunion à 9h, salle Ç-12 {€5} [ok] ~ü ñ\n"));
		self::assertFalse(Gsm7::fits('Hôtel du Lac'));
		self::assertFalse(Gsm7::fits('l’atelier'));
		self::assertFalse(Gsm7::fits('Merci 🙂'));
	}

	public function testFoldKeepsWhatFitsAndReplacesTheRest(): void
	{
		self::assertSame('Hotel du Lac, fete à Noel', Gsm7::fold('Hôtel du Lac, fête à Noël'));
		self::assertSame('Réunion à 9h', Gsm7::fold('Réunion à 9h'));
		self::assertSame("l'atelier \"bientot\" - 10...", Gsm7::fold('l’atelier «bientôt» – 10…'));
		self::assertSame('coeur, Francais', Gsm7::fold('cœur, Français'));
		self::assertSame('Merci ?', Gsm7::fold('Merci 🙂'));
		self::assertTrue(Gsm7::fits(Gsm7::fold('Ţară, łódź, Straße, ÀÉÎÕÜ « » 🙂')));
	}

	public function testSegments(): void
	{
		self::assertSame(0, Gsm7::segments(''));
		self::assertSame(1, Gsm7::segments(\str_repeat('a', 160)));
		self::assertSame(2, Gsm7::segments(\str_repeat('a', 161)));
		self::assertSame(2, Gsm7::segments(\str_repeat('a', 306)));
		self::assertSame(3, Gsm7::segments(\str_repeat('a', 307)));
		// An extension character takes two.
		self::assertSame(2, Gsm7::segments(\str_repeat('a', 159) . '€'));
		// One character outside the alphabet: UCS-2, 70 then 67.
		self::assertSame(1, Gsm7::segments('ô' . \str_repeat('a', 69)));
		self::assertSame(2, Gsm7::segments('ô' . \str_repeat('a', 70)));
		self::assertSame(3, Gsm7::segments('ô' . \str_repeat('a', 134)));
		// An emoji is two UTF-16 units.
		self::assertSame(2, Gsm7::segments('🙂' . \str_repeat('a', 69)));
	}

	public function testRenderedMessage(): void
	{
		$plain = new RenderedMessage('Hello');
		$rich  = new RenderedMessage('Hello', '<p>Hello</p>', 'Hi');

		self::assertFalse($plain->hasHtml());
		self::assertTrue($rich->hasHtml());
		self::assertSame('Hi', $rich->subject);
		self::assertSame(1, $plain->smsSegments());
	}
}
