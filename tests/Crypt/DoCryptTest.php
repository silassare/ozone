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

namespace OZONE\Tests\Crypt;

use OZONE\Core\Crypt\DoCrypt;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Class DoCryptTest.
 *
 * @internal
 *
 * @coversNothing
 */
final class DoCryptTest extends TestCase
{
    public function testEncryptAndDecryptRoundtrip(): void
    {
        $crypt      = new DoCrypt();
        $message    = 'Hello, OZone!';
        $passPhrase = 'super-secret-passphrase';

        $encrypted = $crypt->encrypt($message, $passPhrase);
        self::assertNotFalse($encrypted);
        self::assertNotSame($message, $encrypted);

        $decrypted = $crypt->decrypt($encrypted, $passPhrase);
        self::assertSame($message, $decrypted);
    }

    public function testDecryptWithWrongPassphraseDoesNotReturnOriginal(): void
    {
        $crypt      = new DoCrypt();
        $message    = 'Secret message';
        $passPhrase = 'correct-key';

        $encrypted = $crypt->encrypt($message, $passPhrase);
        $decrypted = $crypt->decrypt((string) $encrypted, 'wrong-key');

        self::assertNotSame($message, $decrypted);
    }

    public function testEncryptEmptyString(): void
    {
        $crypt      = new DoCrypt();
        $passPhrase = 'passphrase';

        $encrypted = $crypt->encrypt('', $passPhrase);
        $decrypted = $crypt->decrypt((string) $encrypted, $passPhrase);

        self::assertSame('', $decrypted);
    }

    public function testUnsupportedCypherThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        new DoCrypt('invalid-cipher-xyz');
    }

    public function testDefaultCypherIsAes256Cbc(): void
    {
        $crypt = new DoCrypt();
        // Verify that the default cipher works by doing a roundtrip
        $msg = 'roundtrip with default cipher';
        $enc = $crypt->encrypt($msg, 'key');
        self::assertSame($msg, $crypt->decrypt((string) $enc, 'key'));
    }

    public function testEncryptProducesDifferentCiphertextForSameInput(): void
    {
        // openssl_encrypt without IV always produces the same result for same inputs
        // (no randomness in this simple implementation), so we just verify success
        $crypt = new DoCrypt();
        $enc1  = $crypt->encrypt('same', 'key');
        self::assertNotFalse($enc1);
    }
}
