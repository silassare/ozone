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

namespace OZONE\Tests\Auth\TwoFactor;

use OZONE\Core\Auth\TwoFactor\TOTP;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class TOTPTest extends TestCase
{
	/**
	 * Base32-encoded form of the 20-byte RFC 4226 seed '12345678901234567890'.
	 *
	 * Used to reproduce RFC 4226 appendix D HOTP counter vectors via TOTP
	 * (counter = floor(time / step)).
	 */
	private const RFC_SEED = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

	// generateSecret -------------------------------------------------------

	public function testGenerateSecretContainsNoPadding(): void
	{
		$secret = TOTP::generateSecret();
		self::assertStringNotContainsString('=', $secret);
	}

	public function testGenerateSecretContainsOnlyValidBase32Chars(): void
	{
		$secret = TOTP::generateSecret();
		self::assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
	}

	public function testGenerateSecretDefaultLengthIs32Characters(): void
	{
		// 20 bytes * 8 bits = 160 bits; 160 / 5 = 32 base32 chars (no trailing padding needed).
		$secret = TOTP::generateSecret(20);
		self::assertSame(32, \strlen($secret));
	}

	public function testGenerateSecretIsUniqueAcrossCalls(): void
	{
		$secrets = [];

		for ($i = 0; $i < 5; ++$i) {
			$secrets[] = TOTP::generateSecret();
		}

		self::assertSame(5, \count(\array_unique($secrets)));
	}

	// base32Encode / base32Decode ------------------------------------------

	public function testBase32EncodeEmptyStringReturnsEmpty(): void
	{
		self::assertSame('', TOTP::base32Encode(''));
	}

	public function testBase32DecodeEmptyStringReturnsEmpty(): void
	{
		self::assertSame('', TOTP::base32Decode(''));
	}

	public function testBase32RoundTripPreservesArbitraryInput(): void
	{
		$inputs = ['a', 'hello', 'Hello!', "\x00\xFF\xAB", '12345678901234567890'];

		foreach ($inputs as $raw) {
			$encoded = TOTP::base32Encode($raw);
			self::assertSame($raw, TOTP::base32Decode($encoded), "Round-trip failed for input: {$raw}");
		}
	}

	public function testBase32EncodeRFCSeedProducesKnownString(): void
	{
		// The RFC 4226 Appendix D seed is ASCII '12345678901234567890' (20 bytes).
		// Its base32 representation (without trailing '=' padding) is a fixed constant.
		$encoded = \rtrim(TOTP::base32Encode('12345678901234567890'), '=');
		self::assertSame(self::RFC_SEED, $encoded);
	}

	public function testBase32DecodeIsCaseInsensitive(): void
	{
		$upper = TOTP::base32Decode('GEZDGNBV');
		$lower = TOTP::base32Decode('gezdgnbv');
		self::assertSame($upper, $lower);
	}

	public function testBase32DecodeToleratesTrailingPaddingOrItsAbsence(): void
	{
		$with_padding    = TOTP::base32Decode('ME======');
		$without_padding = TOTP::base32Decode('ME');
		self::assertSame($with_padding, $without_padding);
	}

	// compute --------------------------------------------------------------

	public function testComputeReturnsZeroPaddedSixDigitNumericString(): void
	{
		$code = TOTP::compute(self::RFC_SEED, 30);
		self::assertMatchesRegularExpression('/^\d{6}$/', $code);
	}

	public function testComputeRFCVectorAtT30CounterOne(): void
	{
		// RFC 4226 Appendix D, counter=1 -> 287082.
		// TOTP at T=30 with step=30 -> counter = floor(30/30) = 1.
		self::assertSame('287082', TOTP::compute(self::RFC_SEED, 30));
	}

	public function testComputeRFCVectorAtT60CounterTwo(): void
	{
		// RFC 4226 Appendix D, counter=2 -> 359152.
		self::assertSame('359152', TOTP::compute(self::RFC_SEED, 60));
	}

	public function testComputeRFCVectorAtT90CounterThree(): void
	{
		// RFC 4226 Appendix D, counter=3 -> 969429.
		self::assertSame('969429', TOTP::compute(self::RFC_SEED, 90));
	}

	public function testComputeIsDeterministicWithinSameTimeStep(): void
	{
		// T=30 and T=59 both yield counter = floor(x/30) = 1.
		self::assertSame(
			TOTP::compute(self::RFC_SEED, 30),
			TOTP::compute(self::RFC_SEED, 59)
		);
	}

	public function testComputeChangesAcrossStepBoundary(): void
	{
		self::assertNotSame(
			TOTP::compute(self::RFC_SEED, 30),  // counter=1
			TOTP::compute(self::RFC_SEED, 60)   // counter=2
		);
	}

	public function testComputeHonors8DigitCodeOption(): void
	{
		$code = TOTP::compute(self::RFC_SEED, 30, 8);
		self::assertMatchesRegularExpression('/^\d{8}$/', $code);
		self::assertSame(8, \strlen($code));
	}

	// verify ---------------------------------------------------------------

	public function testVerifyAcceptsCurrentCode(): void
	{
		$code = TOTP::compute(self::RFC_SEED, 30);
		self::assertTrue(TOTP::verify(self::RFC_SEED, $code, 30));
	}

	public function testVerifyRejectsWrongCode(): void
	{
		self::assertFalse(TOTP::verify(self::RFC_SEED, '000000', 30));
	}

	public function testVerifyToleratesPreviousStepWithinWindowOne(): void
	{
		// Code for counter=1 (T=30..59) must be accepted at T=60 (counter=2)
		// with window=1: check range [counter-1, counter+1] = [1, 3], so counter=1 is in range.
		$code = TOTP::compute(self::RFC_SEED, 30); // counter=1
		self::assertTrue(TOTP::verify(self::RFC_SEED, $code, 60, 1));
	}

	public function testVerifyRejectsCodeTwoStepsOutside(): void
	{
		// Code for counter=1 (T=30) rejected at T=90 (counter=3) with window=1
		// because range is [2, 4] and counter=1 is not in that range.
		$code = TOTP::compute(self::RFC_SEED, 30); // counter=1
		self::assertFalse(TOTP::verify(self::RFC_SEED, $code, 90, 1));
	}

	public function testVerifyWithWindowZeroOnlyAcceptsCurrentStep(): void
	{
		$code = TOTP::compute(self::RFC_SEED, 30); // counter=1
		self::assertTrue(TOTP::verify(self::RFC_SEED, $code, 30, 0));
		// At T=60 with window=0 only counter=2 is checked; code is for counter=1.
		self::assertFalse(TOTP::verify(self::RFC_SEED, $code, 60, 0));
	}

	// buildUri -------------------------------------------------------------

	public function testBuildUriStartsWithOtpAuthScheme(): void
	{
		$uri = TOTP::buildUri('MyApp', 'user@example.com', 'ABCDEFGH');
		self::assertStringStartsWith('otpauth://totp/', $uri);
	}

	public function testBuildUriContainsSecretParamWithoutPadding(): void
	{
		// Trailing '=' padding must be stripped from the secret in the URI.
		$uri = TOTP::buildUri('MyApp', 'user@example.com', 'ABCDEFGH==');
		self::assertStringContainsString('secret=ABCDEFGH', $uri);
		self::assertStringNotContainsString('secret=ABCDEFGH==', $uri);
	}

	public function testBuildUriContainsIssuerParam(): void
	{
		$uri = TOTP::buildUri('TestIssuer', 'user@example.com', 'ABCDEFGH');
		self::assertStringContainsString('issuer=TestIssuer', $uri);
	}

	public function testBuildUriContainsAlgorithmParam(): void
	{
		$uri = TOTP::buildUri('MyApp', 'user@example.com', 'ABCDEFGH');
		self::assertStringContainsString('algorithm=SHA1', $uri);
	}

	public function testBuildUriContainsDigitsParam(): void
	{
		$uri6 = TOTP::buildUri('MyApp', 'user@example.com', 'ABCDEFGH', 6);
		$uri8 = TOTP::buildUri('MyApp', 'user@example.com', 'ABCDEFGH', 8);
		self::assertStringContainsString('digits=6', $uri6);
		self::assertStringContainsString('digits=8', $uri8);
	}

	public function testBuildUriContainsPeriodParam(): void
	{
		$uri = TOTP::buildUri('MyApp', 'user@example.com', 'ABCDEFGH', 6, 60);
		self::assertStringContainsString('period=60', $uri);
	}

	public function testBuildUriUrlEncodesLabelSpecialChars(): void
	{
		// Label = rawurlencode(issuer . ':' . account)
		$uri = TOTP::buildUri('My App', 'user@example.com', 'ABCDEFGH');
		self::assertStringContainsString('My%20App%3Auser%40example.com', $uri);
	}
}
